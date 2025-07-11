<?php

namespace App\Http\Controllers;

use App\Models\Component;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockLevel;
use App\Models\StockLevelLot;
use App\Models\Warehouse;
use App\Models\StockMovement;
use App\Models\LotNumber;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Remove the specified resource from storage.
     */
    public function destroy(StockLevel $stockLevel)
    {
        //
    }
}
