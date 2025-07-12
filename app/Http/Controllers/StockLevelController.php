<?php

namespace App\Http\Controllers;

use App\Models\Component;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemShortfall;
use App\Models\StockLevel;
use App\Models\StockLevelLot;
use App\Models\Warehouse;
use App\Models\StockMovement;
use App\Models\LotNumber;
use App\Services\ShortfallService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Exceptions\BusinessRuleException;

class StockLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Elenco ordini fornitore che attendono ancora la registrazione a stock.
     *
     * Mostra solo:
     *  • ordini con supplier_id valorizzato (≠ null)
     *
     * @return \Illuminate\View\View
     */
    public function indexEntry()
    {
        $supplierOrders = Order::with([
                'supplier',
                'orderNumber',
                'items.component',
                'stockLevelLots',
            ])
            ->whereNotNull('supplier_id')        // ordine di acquisto
            ->orderBy('delivery_date', 'asc')
            ->paginate(15);

        return view('pages.warehouse.entry', compact('supplierOrders'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Registra (o aggiorna) un carico di magazzino per un ordine fornitore.
     *
     * Richiesta AJAX dal modale:
     *  - order_id        : id ordine fornitore (nullable in create-mode)
     *  - component_code  : codice del componente (es. CMP-001)
     *  - qty_received    : quantità caricata
     *  - lot_supplier    : lotto fornitore (string)
     *  - internal_lot_code    : lotto interno (facoltativo: viene generato se vuoto)
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeEntry(Request $request): JsonResponse
    {
        /* 1 ‧ VALIDAZIONE ------------------------------------------------ */
        $data = $request->validate([
            'order_id'          => 'nullable|exists:orders,id',
            'component_code'    => 'required|string|exists:components,code',
            'qty_received'      => 'required|numeric|min:0.01',
            'lot_supplier'      => 'nullable|string|max:50',
            'internal_lot_code' => 'required|string|max:50',
        ], [
            'internal_lot_code.required' => 'Inserisci o genera il lotto interno.',
        ]);

        /* 1-bis ‧ Controllo short-fall riga -------------------------------- */
        if (!empty($data['order_id'])) {

            $orderItem = OrderItem::where('order_id', $data['order_id'])
                ->whereHas('component', fn($q) => $q->where('code', $data['component_code']))
                ->first();

            if ($orderItem &&
                OrderItemShortfall::where('order_item_id', $orderItem->id)->exists()) {

                return response()->json([
                    'success' => false,
                    'blocked' => 'shortfall',
                    'message' => 'La quantità mancante per questo componente è già stata presa in carico da un ordine di recupero. Registra la consegna sullo short-fall.',
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            /* 2 ‧ CONFERMA LOTTO PRENOTATO --------------------------------- */
            $lotNumber = LotNumber::where('code', $data['internal_lot_code'])
                ->where('status', 'reserved')
                ->first();

            if (! $lotNumber) {
                return response()->json([
                    'success' => false,
                    'message' => "Lotto {$data['internal_lot_code']} già usato o non prenotato.",
                ], 422);
            }

            /* 3 ‧ LOOK-UP COMPONENTE e MAGAZZINO --------------------------- */
            $component = Component::whereCode($data['component_code'])->firstOrFail();
            $warehouse = Warehouse::firstWhere('code', 'MG-STOCK');

            /* 4 ‧ BLOCCO STOCK-LEVEL  -------------------------------------- */
            $stockLevel = StockLevel::firstOrCreate([
                'component_id' => $component->id,
                'warehouse_id' => $warehouse->id,
            ]);

            /* 5 ‧ CREAZIONE LOTTO  ----------------------------------------- */
            $lot = $stockLevel->lots()->firstOrNew([
                'internal_lot_code' => $data['internal_lot_code'],
            ]);

            if ($lot->exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Questo lotto interno è già stato registrato.',
                ], 422);
            }

            $lot->fill([
                'supplier_lot_code' => $data['lot_supplier'] ?: null,
                'quantity'          => $data['qty_received'],
            ])->save();

            /* 6 ‧ COLLEGA LOT_NUMBER  -------------------------------------- */
            $lot->lot_number_id = $lotNumber->id;
            $lot->save();
            $lotNumber->update([
                'status'             => 'confirmed',
                'stock_level_lot_id' => $lot->id,
            ]);

            /* 7 ‧ RICALCOLA QUANTITÀ AGGREGATA ----------------------------- */
            $stockLevel->quantity = $stockLevel->total_quantity;
            $stockLevel->save();

            /* 8 ‧ AGGANCIA ALL’ORDINE (SE C’È) ----------------------------- */
            if ($data['order_id']) {
                /** @var Order $order */
                $order = Order::with('stockLevelLots')
                    ->whereKey($data['order_id'])
                    ->whereHas('orderNumber', fn ($q) => $q->where('order_type', 'supplier'))
                    ->first();

                if (! $order) {
                    throw new \RuntimeException('Ordine fornitore non trovato o non valido.');
                }

                // evita duplicati (lotto già collegato)
                if (! $order->stockLevelLots->contains($lot->id)) {
                    $order->stockLevelLots()->attach($lot->id);
                }

                // gestione riga ordine
                $orderItem = OrderItem::firstOrNew([
                    'order_id'     => $order->id,
                    'component_id' => $component->id,
                ]);

                if (!$orderItem->exists) {
                    // nuova riga → quantità + prezzo da pivot component_supplier
                    $unitPrice = $component->componentSuppliers()
                                ->where('supplier_id', $order->supplier_id)
                                ->value('last_cost') ?? 0;          // fallback 0 €

                    $orderItem->fill([
                        'quantity'   => $data['qty_received'],
                        'unit_price' => $unitPrice,
                    ]);
                }

                $orderItem->save();

                $deltaQty = $data['qty_received'];           // sempre la quantità che stai registrando
                $deltaVal = $deltaQty * $orderItem->unit_price;

                $order->increment('total', $deltaVal); // aggiorna il totale dell'ordine
            }

            /* 9 ‧ LOG MOVIMENTO  ------------------------------------------- */
            StockMovement::create([
                'stock_level_id' => $stockLevel->id,
                'type'           => 'IN',
                'quantity'       => $data['qty_received'],
                'note'           => 'Carico lotto interno ' . $lot->internal_lot_code,
            ]);

            DB::commit();

            /* 10 ‧ VERIFICA PIVOT (debug) ---------------------------------- */
            if (isset($order) && ! $order->fresh()->stockLevelLots->contains($lot->id)) {
                // Se non dovesse esserci, loggo per futura analisi
                Log::warning("Pivot non aggiornata per ordine {$order->id} / lotto {$lot->id}");
            }

            return response()->json([
                'success' => true,
                'lot'     => $lot->only(['internal_lot_code', 'supplier_lot_code', 'quantity']),
            ]);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Errore SQL storeEntry', ['msg' => $e->getMessage(), 'data' => $data]);
            return response()->json([
                'success' => false,
                'message' => 'Errore database: '.$e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Errore generico storeEntry', ['msg' => $e->getMessage(), 'data' => $data]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(StockLevel $stockLevel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StockLevel $stockLevel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StockLevel $stockLevel)
    {
        //
    }

    /**
     * Aggiorna una registrazione di magazzino già esistente.
     *
     * Permette di modificare quantità ricevuta e lotto fornitore;
     * non tocca i dati di testata dell’ordine.
     *
     * Regole di dominio:
     *  - il lotto interno è immutabile
     *  - se la riga è già in uno short-fall, blocca la modifica
     *  - se si riduce la quantità, deve esserci giacenza disponibile
     *  - aggiorna stock_level, order_items, orders.total
     *  - logga un movimento IN/OUT per audit
     *
     * @param  Request          $request
     * @param  StockLevelLot    $lot          lotto da aggiornare (route-model-binding)
     * @param  ShortfallService $svc
     * @return JsonResponse
     */
    public function updateEntry(Request $request, ShortfallService $svc): JsonResponse
    {
        /* 1‧ VALIDAZIONE INPUT ------------------------------------------------ */
        $payload = $request->validate([
            'lots'                  => ['required','array','min:1'],
            'lots.*.id'             => ['required','exists:stock_level_lots,id'],
            'lots.*.qty'            => ['required','numeric','min:0.01'],
            'lots.*.lot_supplier'   => ['nullable','string','max:50'],
        ]);

        Log::debug('⏩ updateEntry START', ['lots' => $payload['lots']]);

        $updated          = [];
        $shortfallCreated = false;
        $followUpId       = null;
        $followUpNumber   = null;

        /* 2‧ TRANSAZIONE ------------------------------------------------------ */
        try {
            DB::transaction(function () use (
                $payload, &$updated, $svc,
                &$shortfallCreated, &$followUpId, &$followUpNumber
            ) {

                foreach ($payload['lots'] as $lotData) {
                    Log::debug('🔍 Processing lot', $lotData);

                    /** @var StockLevelLot $lot */
                    $lot = StockLevelLot::with(['stockLevel','stockLevel.component'])
                        ->lockForUpdate()
                        ->find($lotData['id']);

                    if (! $lot) {
                        Log::warning('❌ Lot not found', ['id' => $lotData['id']]);
                        continue;
                    }

                    /* === ORDINE COLLEGATO (via pivot) ======================= */
                    $order = Order::whereHas('stockLevelLots', fn($q) =>
                                $q->where('stock_level_lots.id', $lot->id)
                            )->first();

                    $stockLevel = $lot->stockLevel;
                    $delta      = $lotData['qty'] - $lot->quantity;

                    Log::debug('ℹ️  Delta calcolato', [
                        'lot_id'    => $lot->id,
                        'old_qty'   => $lot->quantity,
                        'new_qty'   => $lotData['qty'],
                        'delta'     => $delta,
                        'stock_qty' => $stockLevel->quantity,
                    ]);

                    /* 3-a ‧ BLOCCA se la riga è già in uno short-fall -------- */
                    $alreadySf = $order && OrderItemShortfall::whereRelation('orderItem',
                                    'order_id',     $order->id)
                                ->whereRelation('orderItem',
                                    'component_id', $stockLevel->component_id)
                                ->exists();

                    if ($alreadySf) {
                        Log::info('🚫 alreadyShortfall', ['lot_id' => $lot->id]);
                        throw new \App\Exceptions\BusinessRuleException('alreadyShortfall');
                    }

                    /* 3-b ‧ BLOCCA se manca giacenza ------------------------- */
                    if ($delta < 0 && ($stockLevel->quantity + $delta) < 0) {
                        Log::info('🚫 insufficient_stock', ['lot_id' => $lot->id]);
                        throw new \App\Exceptions\BusinessRuleException('insufficient_stock');
                    }

                    /* 4‧ APPLICA VARIAZIONE ---------------------------------- */
                    if ($delta !== 0.0 || $lotData['lot_supplier'] !== $lot->supplier_lot_code) {

                        $lot->update([
                            'quantity'          => $lotData['qty'],
                            'supplier_lot_code' => $lotData['lot_supplier'] ?: $lot->supplier_lot_code,
                        ]);

                        $stockLevel->increment('quantity', $delta);

                        StockMovement::create([
                            'stock_level_id' => $stockLevel->id,
                            'type'           => $delta > 0 ? 'IN' : 'OUT',
                            'quantity'       => abs($delta),
                            'note'           => 'Modifica lotto ' . $lot->internal_lot_code,
                        ]);

                        Log::debug('✅ Lotto aggiornato', ['lot_id' => $lot->id]);

                        $updated[] = [
                            'id'           => $lot->id,
                            'qty'          => $lot->quantity,
                            'lot_supplier' => $lot->supplier_lot_code,
                        ];

                        /* 5‧ DELTA NEGATIVO → possibile nuovo short-fall ------ */
                        if ($delta < 0 && $order) {
                            $newSF = $svc->capture($order);   // null se nessun gap
                            if ($newSF) {
                                $shortfallCreated = true;
                                $followUpId       = $newSF->id;
                                $followUpNumber   = $newSF->number;
                            }
                        }
                    }
                }
            });
        } catch (BusinessRuleException $e) {
            return response()->json([
                'success' => false,
                'blocked' => $e->getMessage(),     // alreadyShortfall | insufficient_stock
                'message' => $e->getMessage() === 'alreadyShortfall'
                    ? 'Non è possibile modificare questa riga perché esiste un ordine di recupero. Modifica la quantità direttamente lì.'
                    : 'Non c’è abbastanza giacenza per ridurre questa quantità.',
            ], 422);
        }
        
        Log::debug('⏹ updateEntry END', [
            'updated_count' => count($updated),
            'shortfall'     => $shortfallCreated,
        ]);

        /* 6‧ RISPOSTA --------------------------------------------------------- */
        return response()->json([
            'success'             => true,
            'updated'             => $updated,
            'shortfall_created'   => $shortfallCreated,
            'follow_up_order_id'  => $followUpId,
            'follow_up_number'    => $followUpNumber,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StockLevel $stockLevel)
    {
        //
    }
}
