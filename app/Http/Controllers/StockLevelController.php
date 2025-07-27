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
use App\Models\StockReservation;
use App\Models\LotNumber;
use App\Services\ShortfallService;
use App\Services\ReservationService;   
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
    public function indexEntry(Request $request)
    {
        /*────────── PARAMETRI QUERY ──────────*/
        $sort    = $request->input('sort', 'order_number');    // default ↑
        $dir     = $request->input('dir',  'desc') === 'asc' ? 'asc' : 'desc';
        $filters = $request->input('filter', []);

        /*────────── WHITELIST ORDINABILI ─────*/
        $allowedSorts = ['order_number', 'supplier', 'delivery_date'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'delivery_date';
        }

        /*────────── QUERY BASE ───────────────*/
        $supplierOrders = Order::query()
            ->with(['supplier:id,name,email,vat_number,address', 'orderNumber:id,number,order_type'])
            ->whereHas('orderNumber', fn ($q) =>
                $q->where('order_type', 'supplier')
            )        // sono già ricevimenti
            /*───── FILTRI ─────────────────────*/
            ->when($filters['order_number'] ?? null,
                fn ($q, $v) => $q->whereHas('orderNumber',
                                    fn ($q) => $q->where('number', 'like', "%$v%")))
            ->when($filters['supplier'] ?? null,
                fn ($q, $v) => $q->whereHas('supplier',
                                    fn ($q) => $q->where('name', 'like', "%$v%")))
            ->when($filters['delivery_date'] ?? null,
                fn ($q, $v) => $q->whereDate('delivery_date', 'like', "%$v%"))
            /*───── ORDINAMENTO ───────────────*/
            ->when($sort === 'supplier', function ($q) use ($dir) {
                $q->join('suppliers as s', 'orders.supplier_id', '=', 's.id')
                ->orderBy('s.name', $dir)
                ->select('orders.*');
            })
            ->when($sort === 'order_number', function ($q) use ($dir) {
                $q->join('order_numbers as on', 'orders.order_number_id', '=', 'on.id')
                ->orderBy('on.number', $dir)
                ->select('orders.*');
            }, function ($q) use ($sort, $dir) {
                $q->orderBy($sort, $dir);
            })
            ->paginate(15)
            ->appends($request->query());

        return view(
            'pages.warehouse.entry',
            compact('supplierOrders', 'sort', 'dir', 'filters')
        );
    }

    /**
     * Elenco righe d’ordine CLIENTE ancora da evadere (uscite magazzino).
     *
     * • Usa la VIEW `v_order_item_phase_qty` per sapere quante unità
     *   di ogni riga stazionano nella fase scelta (KPI).
     * • Mostra solo le righe con qty_in_phase > 0.
     * • Filtri & ordinamento passati via query-string ma
     *   **senza** ricaricare pagina (gestiti da Livewire/Alpine).
     *
     * Route protetta dal permesso stock.exit.
     *
     * @return \Illuminate\View\View
     */
    public function indexExit(Request $request)
    {
        /* ───────────── parametri da query-string ───────────── */
        $phase   = (int)   $request->input('phase', 0);
        $sort    =         $request->input('sort',  'delivery_date');
        $dir     =         $request->input('dir',   'asc') === 'desc' ? 'desc' : 'asc';
        $filters = (array) $request->input('filter', []);
        $perPage = (int)   $request->input('per_page', 100);
        $perPage = in_array($perPage, [100,250,500], true) ? $perPage : 100;

        /* KPI card – conteggio pezzi per fase */
        $kpiCounts = DB::table('v_order_item_phase_qty')
            ->selectRaw('phase, SUM(qty_in_phase) AS qty')
            ->where('qty_in_phase', '>', 0)
            ->groupBy('phase')
            ->pluck('qty', 'phase');

        /* whitelist ordinamenti                      */
        $allowedSorts = [
            'customer','order_number','product',
            'order_date','delivery_date','value','qty_in_phase',
        ];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'delivery_date';
        }

        /* query righe                                */
        $phase = $phase;   // dall’input

        $pqSub = DB::table('v_order_item_phase_qty')
            ->select('order_item_id', DB::raw('SUM(qty_in_phase) AS qty_in_phase'))
            ->where('phase', $phase)
            ->groupBy('order_item_id');

        $exitRows = OrderItem::query()
            ->joinSub($pqSub, 'pq', 'pq.order_item_id', '=', 'order_items.id')
            ->join('orders   as o',  'o.id',  '=', 'order_items.order_id')
            ->leftJoin('customers as c',      'c.id', '=', 'o.customer_id')
            ->leftJoin('order_numbers as on', 'on.id', '=', 'o.order_number_id')
            ->leftJoin('products as p',       'p.id', '=', 'order_items.product_id')
            ->addSelect([
                'order_items.*',
                'pq.qty_in_phase',
                DB::raw('(order_items.quantity * order_items.unit_price) AS value'),

                'c.company        as customer',
                'on.number        as order_number',
                'p.sku            as product',
                'o.ordered_at     as order_date',
                'o.delivery_date',
            ])
            ->whereNotNull('o.customer_id') // ordini con cliente
            /* filtri dinamici ----------------------- */
            ->when($filters['customer']      ?? null,
                fn ($q, $v) => $q->where('c.company',   'like', "%$v%"))
            ->when($filters['order_number']  ?? null,
                fn ($q, $v) => $q->where('on.number',   'like', "%$v%"))
            ->when($filters['product']       ?? null,
                fn ($q, $v) => $q->where(function ($qq) use ($v) {
                        $qq->where('p.sku',  'like', "%$v%")
                        ->orWhere('p.name','like', "%$v%");
                }))
            ->when($filters['order_date']    ?? null,
                fn ($q, $v) => $q->whereDate('o.ordered_at',    $v))
            ->when($filters['delivery_date'] ?? null,
                fn ($q, $v) => $q->whereDate('o.delivery_date', $v))
            ->when($filters['value']         ?? null,
                fn ($q, $v) => $q->whereRaw(
                    '(order_items.quantity * order_items.unit_price) >= ?', [$v]
                ))

            /* ordinamento --------------------------- */
            ->when($sort === 'customer',
                fn ($q) => $q->orderBy('c.company',       $dir))
            ->when($sort === 'order_number',
                fn ($q) => $q->orderBy('on.number',       $dir))
            ->when($sort === 'product',
                fn ($q) => $q->orderBy('p.sku',           $dir))
            ->when($sort === 'qty_in_phase',
                fn ($q) => $q->orderBy('pq.qty_in_phase', $dir))
            ->when(in_array($sort, ['order_date','delivery_date','value'], true),
                fn ($q) => $q->orderBy(
                    $sort === 'value' ? 'value' : $sort,
                    $dir
                ))

            ->paginate($perPage)
            ->appends($request->query());

        /* view con Livewire <livewire:warehouse.exit-table /> */
        return view('pages.warehouse.exit', [
            'exitRows'  => $exitRows,    // usato per fallback / SEO
            'kpiCounts' => $kpiCounts,
            'phase'     => $phase,
            'sort'      => $sort,
            'dir'       => $dir,
            'filters'   => $filters,
            'perPage'   => $perPage,
        ]);
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
                ->lockForUpdate()
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
                    ->lockForUpdate()
                    ->whereKey($data['order_id'])
                    ->whereHas('orderNumber', fn ($q) => $q->where('order_type', 'supplier'))
                    ->firstOrFail();

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

            /* 2-i  Prenotazioni cliente (RESERVE) ------------------------ */
            app(ReservationService::class)->attach($lot);
            
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
        } catch (BusinessRuleException $e) {
            DB::rollBack();
            Log::error('Errore di business storeEntry', ['msg' => $e->getMessage(), 'data' => $data]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        }catch (QueryException $e) {
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
     * Mostra i livelli di stock per un componente specifico.
     */
    public function showStock(int $componentId): JsonResponse
    {
        /* -------------------------------------------------------------
        * 2) Giacenze attuali (lotto per lotto)
        *    - qty > 0
        * ----------------------------------------------------------- */
        $levels = StockLevel::query()
            ->select([
                'stock_levels.id',
                'stock_levels.component_id',
                'stock_levels.warehouse_id',
                'stock_levels.quantity',
                'c.code       as component_code',
                'c.description as component_desc',
                'c.unit_of_measure as uom',
                'w.code       as warehouse_code',
                'sll.internal_lot_code as internal_lot',
            ])
            ->join('components as c',  'c.id',  '=', 'stock_levels.component_id')
            ->join('warehouses as w',  'w.id',  '=', 'stock_levels.warehouse_id')
            ->leftJoin('stock_level_lots as sll', 'sll.stock_level_id', '=', 'stock_levels.id')
            ->where('stock_levels.component_id', $componentId)
            ->where('stock_levels.quantity',    '>', 0)
            ->orderBy('w.code')
            ->orderBy('sll.internal_lot_code')
            ->get();

        /* -----------------------------------------------------------------
        * RISERVATO  –  somma per ogni singolo stock_level_id
        * ---------------------------------------------------------------- */
        $reservedByLevel = StockReservation::query()
            ->selectRaw('stock_level_id, SUM(quantity) as qty')
            ->whereIn('stock_level_id', $levels->pluck('id'))   // <-- filtro sui lotti del componente
            ->groupBy('stock_level_id')
            ->pluck('qty', 'stock_level_id');                   // [stock_level_id => qty]

        /* -----------------------------------------------------------------
        * Costruzione righe tabella
        * ---------------------------------------------------------------- */
        $rows = collect();

        foreach ($levels as $row) {

            $reserved = $reservedByLevel[$row->id] ?? 0;      // riservata su quel lotto
            $free     = $row->quantity - $reserved;           // disponibile reale

            /* -------- riga MAGAZZINO (solo se >0) ------------------------- */
            if ($free > 0) {
                $rows->push([
                    'id'             => $row->id,
                    'component_code' => $row->component_code,
                    'component_desc' => $row->component_desc,
                    'uom'            => $row->uom,
                    'warehouse_code' => $row->warehouse_code, // es. MG-STOCK
                    'internal_lot'   => $row->internal_lot,
                    'qty'            => number_format($free, 3, '.', ''),
                ]);
            }

            /* -------- riga RISERVATO (solo se >0) ------------------------- */
            if ($reserved > 0) {
                $rows->push([
                    'id'             => 'R-'.$row->id,        // chiave unica
                    'component_code' => $row->component_code,
                    'component_desc' => $row->component_desc,
                    'uom'            => $row->uom,
                    'warehouse_code' => 'Riservato',
                    'internal_lot'   => $row->internal_lot,
                    'qty'            => number_format($reserved, 3, '.', ''),
                ]);
            }
        }

        /* -----------------------------------------------------------------
        * Risposta JSON
        * ---------------------------------------------------------------- */
        return response()->json([
            'rows' => $rows->values(),
        ]);
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

                    /* 3-a ‧ BLOCCA se la riga è già in uno short-fall -------- */
                    $alreadySf = $order && OrderItemShortfall::whereRelation('orderItem',
                                    'order_id',     $order->id)
                                ->whereRelation('orderItem',
                                    'component_id', $stockLevel->component_id)
                                ->exists();

                    if ($alreadySf) {
                        throw new BusinessRuleException('alreadyShortfall');
                    }

                    /* 3-b ‧ BLOCCA se manca giacenza ------------------------- */
                    if ($delta < 0 && ($stockLevel->quantity + $delta) < 0) {
                        throw new BusinessRuleException('insufficient_stock');
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
