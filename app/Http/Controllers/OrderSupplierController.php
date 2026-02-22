<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNumber;
use App\Models\OrderItemShortfall;
use App\Models\StockLevel;
use App\Models\StockLevelLot;
use App\Models\Supplier;
use App\Services\ShortfallService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema; 
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrderSupplierController extends Controller
{
    /**
     * Display a listing of supplier purchase orders.
     *
     * @param  Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        /*────────── PARAMETRI QUERY ──────────*/
        $sort    = $request->input('sort', 'ordered_at');      // campo sort default
        $dir     = $request->input('dir',  'desc') === 'asc' ? 'asc' : 'desc';
        $filters = $request->input('filter', []);              // array filtri

        /*────────── WHITELIST ORDINABILI ─────*/
        $allowedSorts = ['id', 'supplier', 'ordered_at', 'delivery_date', 'total'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'ordered_at';
        }

        /*────────── QUERY BASE ───────────────*/
        $orders = Order::query()
            ->with(['supplier:id,name', 'orderNumber:id,number,order_type'])  // eager-load
            ->whereHas('orderNumber', fn ($q) =>
                $q->where('order_type', 'supplier')
            )
            /*───── FILTRI COLUMNA ────────────*/
            ->when($filters['id']          ?? null,
                   fn ($q,$v) => $q->where('id', $v))
            ->when($filters['supplier']    ?? null,
                   fn ($q,$v) => $q->whereHas('supplier',
                                  fn ($q) => $q->where('name','like',"%$v%")))
            ->when($filters['ordered_at']  ?? null,
                   fn ($q,$v) => $q->whereDate('ordered_at',$v))
            ->when($filters['delivery_date'] ?? null,
                   fn ($q,$v) => $q->whereDate('delivery_date',$v))
            ->when($filters['total']       ?? null,
                   fn ($q,$v) => $q->where('total','like',"%$v%"))
            /*───── ORDINAMENTO ──────────────*/
            ->when($sort === 'supplier', function ($q) use ($dir) {
                $q->join('suppliers as s','orders.supplier_id','=','s.id')
                  ->orderBy('s.name',$dir)
                  ->select('orders.*');
            }, function ($q) use ($sort,$dir) {
                $q->orderBy($sort,$dir);
            })
            ->paginate(15)
            ->appends($request->query()); // preserva parametri per i link

        return view(
            'pages.orders.index-suppliers',
            compact('orders','sort','dir','filters')
        );
    }

    /**
     * Mostra le righe di un ordine fornitore.
     * 
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function lines(Order $order): JsonResponse
    {
        abort_unless(
            $order->orderNumber->order_type === 'supplier',
            403,
            'Non è un ordine fornitore'
        );

        /*─────────────────────────────────────────────────────────────
        | 1) Eager-load componenti e lotti (come prima)
        ──────────────────────────────────────────────────────────────*/
        $order->load([
            'items.component:id,code,description,unit_of_measure',
            'stockLevelLots.stockLevel', // lotti già registrati
        ]);

        /*─────────────────────────────────────────────────────────────
        | 2) NEW: Mappa note colori provenienti dagli ordini cliente
        |
        | - La nota "vive" nell'ordine cliente: order_product_variables.color_notes
        | - Match:
        |   OC:  order_items.order_id = generated_by_order_customer_id (riga PO)
        |   Comp: order_product_variables.resolved_component_id = order_items.component_id (riga PO)
        |
        | Chiave mappa: "<oc_id>:<component_id>" => "note"
        ──────────────────────────────────────────────────────────────*/
        $notesMap = [];

        // Safety: se la colonna non esiste (ambiente non aggiornato), non rompiamo la response.
        if (Schema::hasColumn('order_product_variables', 'color_notes')) {

            $ocIds = $order->items
                ->pluck('generated_by_order_customer_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $compIds = $order->items
                ->pluck('component_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (!empty($ocIds) && !empty($compIds)) {

                $rows = DB::table('order_items as oi')
                    ->join('order_product_variables as opv', 'opv.order_item_id', '=', 'oi.id')
                    ->whereIn('oi.order_id', $ocIds)                         // ordini cliente collegati
                    ->whereIn('opv.resolved_component_id', $compIds)         // componente risolto
                    ->whereNotNull('opv.color_notes')
                    ->where('opv.color_notes', '<>', '')
                    ->select([
                        'oi.order_id as oc_id',
                        'opv.resolved_component_id as component_id',
                        'opv.color_notes',
                    ])
                    ->get();

                // Raggruppa + deduplica (se più righe OC matchano lo stesso componente)
                $bucket = []; // key => array<string>
                foreach ($rows as $r) {
                    $key = (int) $r->oc_id . ':' . (int) $r->component_id;
                    $bucket[$key] = $bucket[$key] ?? [];
                    $bucket[$key][] = trim((string) $r->color_notes);
                }

                foreach ($bucket as $k => $arr) {
                    $uniq = array_values(array_unique(array_filter($arr)));
                    $notesMap[$k] = count($uniq) ? implode("\n", $uniq) : null;
                }
            }
        }

        /*─────────────────────────────────────────────────────────────
        | 3) Bucket lotti per componente (come prima)
        ──────────────────────────────────────────────────────────────*/
        $lotsByComp = $order->stockLevelLots
            ->groupBy(fn ($lot) => $lot->stockLevel->component_id);

        /*─────────────────────────────────────────────────────────────
        | 4) Genera righe (una per lotto) + NEW color_notes
        ──────────────────────────────────────────────────────────────*/
        $rows = collect();

        foreach ($order->items as $item) {

            // Key mappa note (se la riga PO è collegata a un OC)
            $ocId = (int) ($item->generated_by_order_customer_id ?? 0);
            $cid  = (int) ($item->component_id ?? 0);
            $key  = ($ocId > 0 && $cid > 0) ? ($ocId . ':' . $cid) : null;

            $colorNotes = $key ? ($notesMap[$key] ?? null) : null;

            $lots = $lotsByComp->get($item->component_id) ?? collect();

            // Se nessun lotto: riga “vuota” per componente
            if ($lots->isEmpty()) {
                $rows->push([
                    'id'            => $item->id,
                    'code'          => $item->component->code,
                    'desc'          => $item->component->description,
                    'unit'          => $item->component->unit_of_measure,
                    'qty'           => $item->quantity,
                    'qty_received'  => 0,
                    'lot_supplier'  => null,
                    'internal_lot'  => null,
                    'price'         => (float) $item->unit_price,
                    'subtot'        => $item->quantity * $item->unit_price,

                    // NEW: note colori (da ordine cliente)
                    'color_notes'   => $colorNotes,
                ]);
            }

            // Se ci sono lotti: una riga per lotto
            foreach ($lots as $lot) {
                $rows->push([
                    'id'            => $item->id,
                    'code'          => $item->component->code,
                    'desc'          => $item->component->description,
                    'unit'          => $item->component->unit_of_measure,
                    'qty'           => $item->quantity,
                    'qty_received'  => $lot->received_quantity,
                    'lot_supplier'  => $lot->supplier_lot_code,
                    'internal_lot'  => $lot->internal_lot_code,
                    'price'         => (float) $item->unit_price,
                    'subtot'        => $item->quantity * $item->unit_price,

                    // NEW: note colori (da ordine cliente)
                    'color_notes'   => $colorNotes,
                ]);
            }
        }

        return response()->json($rows->values());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Salva un nuovo ordine fornitore + righe.
     * Attende nel payload:
     *  - order_number_id  (FK al registro)
     *  - supplier_id
     *  - delivery_date
     *  - lines[ {component_id, quantity, price} … ]
     * 
     * @param  \Illuminate\Http\Request  $request
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_number_id'            => ['required','exists:order_numbers,id'],
            'supplier_id'                => ['required','exists:suppliers,id'],
            'delivery_date'              => ['required','date'],
            'lines'                      => ['required','array','min:1'],
            'lines.*.component_id'       => ['required','distinct','exists:components,id'],
            'lines.*.quantity'           => ['required','numeric','min:0.01'],
            'lines.*.last_cost'              => ['required','numeric','min:0'],
        ],
        [
            'order_number_id.required' => 'Il numero ordine è obbligatorio.',
            'supplier_id.required'     => 'Il fornitore è obbligatorio.',
            'delivery_date.required'   => 'La data di consegna è obbligatoria.',
            'lines.required'           => 'Le righe sono obbligatorie.',
            'lines.array'              => 'Le righe devono essere un array.',
            'lines.min'                => 'Devi fornire almeno una riga.',
            'lines.*.component_id.required' => 'Il componente è obbligatorio.',
            'lines.*.component_id.distinct' => 'Il componente deve essere unico.',
            'lines.*.component_id.exists'   => 'Il componente selezionato non è valido.',
        ]);

        DB::transaction(function () use ($data) {

            /* ---------- Ordine ---------- */
            $order = Order::create([
                'order_number_id' => $data['order_number_id'],   // FK registro
                'supplier_id'     => $data['supplier_id'],
                'total'           => 0,                           // temp
                'ordered_at'      => now(),
                'delivery_date'   => $data['delivery_date'],
            ]);

            /* ---------- Righe & totale ---------- */
            $total = 0;

            foreach ($data['lines'] as $row) {
                $subtotal = $row['quantity'] * $row['last_cost'];

                OrderItem::create([
                    'order_id'     => $order->id,
                    'component_id' => $row['component_id'],
                    'quantity'     => $row['quantity'],
                    'unit_price'   => $row['last_cost'],
                ]);

                $total += $subtotal;
            }

            $order->update(['total' => $total]);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Salva un ordine “vuoto” creato dal modale di registrazione.
     *
     * Attende nel payload JSON:
     *  - order_number_id  FK già prenotato (tabella order_numbers)
     *  - supplier_id      Fornitore
     *  - delivery_date    Data consegna / registrazione / ordine
     *  - bill_number      Numero bolla (-nullable)
     *
     * Ritorna: { success:true, id, number }
     *
     * @param  \Illuminate\Http\Request  $req
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeByRegistration(Request $req): JsonResponse
    {
        /*─── VALIDAZIONE ─────────────────────────────────────────────*/
        $data = $req->validate([
            'order_number_id' => ['required','exists:order_numbers,id','unique:orders,order_number_id'],
            'supplier_id'     => ['required','exists:suppliers,id'],
            'delivery_date'   => ['required','date', 'before_or_equal:today'],
            'bill_number'     => ['required','string','max:50'],
        ],[
            'order_number_id.unique' => 'Questo numero ordine è già stato utilizzato.',
            'bill_number.max'        => 'Il numero bolla non può superare i 50 caratteri.',
            'bill_number.string'     => 'Il numero bolla deve essere una stringa.',
            'bill_number.required'   => 'Il numero bolla è obbligatorio.',
            'delivery_date.required' => 'La data di consegna è obbligatoria.',
            'delivery_date.date'     => 'La data di consegna deve essere una data valida.',
            'supplier_id.required'   => 'Il fornitore è obbligatorio.',
            'supplier_id.exists'     => 'Il fornitore selezionato non esiste.',
            'order_number_id.required' => 'Il numero ordine è obbligatorio.',
            'order_number_id.exists' => 'Il numero ordine selezionato non esiste.',
            'order_number_id.unique' => 'Il numero ordine è già stato utilizzato.',
        ]);

        /*─── TRANSAZIONE ────────────────────────────────────────────*/
        $order = DB::transaction(function () use ($data) {

            /* crea l’ordine in stato “bozza” */
            return Order::create([
                'order_number_id'   => $data['order_number_id'],
                'supplier_id'       => $data['supplier_id'],
                'total'             => 0,
                'ordered_at'        => $data['delivery_date'],   // stessa data per i 3 campi
                'delivery_date'     => $data['delivery_date'],
                'registration_date' => $data['delivery_date'],
                'bill_number'       => $data['bill_number'],
            ]);
        });

        return response()->json([
            'success' => true,
            'id'      => $order->id,
            'number'  => $order->orderNumber->number,
        ]);
    }

    /**
     * CTA "Crea Shortfall" per un ordine fornitore.
     *
     * - Valida l'input (order_id)
     * - Determina $canCreate dai permessi utente
     * - Applica lock row-level su TUTTE le righe dell'ordine fornitore
     * - Chiama direttamente ShortfallService::captureGrouped($order, $canCreate)
     * - Risponde con JSON per il front-end (status, message, summary)
     */
    public function createShortfallHoles(Request $request, ShortfallService $service): JsonResponse
    {
        /* ── 1) Validazione input ─────────────────────────────────────── */
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        Log::info('[@createShortfallHoles] - dati validati', $data);

        /* ── 2) Permessi → boolean $canCreate ─────────────────────────── */
        $canCreate = (bool) optional($request->user())->can('orders.supplier.create');

        Log::info('[@createShortfallHoles] - permessi utente', ['canCreate' => $canCreate]);

        /* ── 3) Transazione + lock su righe ordine ────────────────────── */
        $tx = DB::transaction(function () use ($data, $service, $canCreate) {

            /** @var \App\Models\Order $order */
            $order = Order::query()
                ->where('id', $data['order_id'])
                ->whereNotNull('supplier_id') // sicurezza: solo PO
                ->firstOrFail();

            Log::info('[@createShortfallHoles] - ordine trovato', ['order_id' => $order->id]);

            // Lock row-level su TUTTE le righe della PO per evitare catture concorrenti
            $lineIds = OrderItem::query()
                ->where('order_id', $order->id)
                ->pluck('id');

            Log::info('[@createShortfallHoles] - righe ordine trovate', ['line_ids' => $lineIds]);

            if ($lineIds->isNotEmpty()) {
                OrderItem::query()
                    ->whereIn('id', $lineIds)
                    ->lockForUpdate()
                    ->get();
            }

            // Delega alla logica esistente (calcolo mancanti, raggruppo e — se permesso — creo)
            $res = $service->captureGrouped($order, $canCreate);

            Log::info('[@createShortfallHoles] - risultato cattura', $res);

            // Normalizza un riepilogo coerente con la UI (lasciato identico al tuo)
            $summary = [
                'created_pos'   => (int)   ($res['created_pos']   ?? $res['pos']   ?? 0),
                'created_lines' => (int)   ($res['created_lines'] ?? $res['lines'] ?? 0),
                'covered_qty'   => (float) ($res['covered_qty']   ?? $res['qty']   ?? 0),
            ];

            // ──► (FIX) RITORNA SIA $res CHE $summary
            return compact('res', 'summary');
        }, 3); // retry su deadlock

        /* ── (FIX) Estrai $res e $summary dal risultato della transazione ── */
        $res     = $tx['res'];
        $summary = $tx['summary'];

        Log::info('[@createShortfallHoles] - riepilogo transazione', $summary);

        /* ── 4) Risposta JSON front-end friendly ──────────────────────── */
        if (! $canCreate) {
            // Nessuna creazione: restituiamo 403 + riepilogo informativo
            return response()->json([
                'status'  => 'error',
                'message' => 'Permesso negato: non puoi creare ordini fornitore.',
                'summary' => $summary,
            ], 403);
        }

        // --- SUCCESSO -------------------------------------------------------------

        // Estrai TUTTI i numeri (fallback all'ID se il numero manca)
        $ordersArr = is_array($res['orders'] ?? null) ? $res['orders'] : [];
        $labels = collect($ordersArr)
            ->map(fn ($o) => $o['number'] ?? $o['id'] ?? null)
            ->filter()
            ->values();

        $message = 'Shortfall creato.';
        if (!empty($res['created'])) {
            $count = $labels->count();

            if ($count === 0) {
                // created=true ma nessun numero/id disponibile: messaggio neutro
                $message = 'Shortfall creato.';
            } elseif ($count === 1) {
                $message = 'Creato ordine fornitore #' . $labels[0] . '.';
            } else {
                // Plurale: #X, #Y, #Z
                $message = 'Creati ordini fornitori #' . $labels->implode(', #') . '.';
            }
        } elseif (empty($res['needed'])) {
            $message = 'Nessuno shortfall necessario per questo ordine.';
        }

        return response()->json([
            'status'  => 'ok',
            'message' => $message,
            'summary' => $summary ?? [],
        ]);
    }

    /**
     * Permette di visualizzare un ordine fornitore specifico.
     * 
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showApi(int $id): JsonResponse
    {
        try {
            $order = Order::with([
                    'supplier:id,name,vat_number,email,address',
                    'orderNumber:id,number,order_type',
                    'items.component:id,code,description,unit_of_measure',
                    'stockLevelLots.stockLevel:id,component_id'
                ])
                ->whereHas('orderNumber', fn ($q) => $q->where('order_type','supplier'))
                ->findOrFail($id);

            /*──────────────── NEW: Mappa note colori da Ordini Cliente ────────────────
            | Chiave: "<oc_id>:<component_id>"
            | Valore: string|null (note deduplicate, join con "\n")
            ───────────────────────────────────────────────────────────────────────*/
            $notesMap = [];

            // Safety: se la colonna non esiste in qualche ambiente, non rompiamo nulla
            if (Schema::hasColumn('order_product_variables', 'color_notes')) {

                // OC collegati alle righe di questo PO
                $ocIds = $order->items
                    ->pluck('generated_by_order_customer_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                // componenti presenti nel PO
                $compIds = $order->items
                    ->pluck('component_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($ocIds) && !empty($compIds)) {

                    $rows = DB::table('order_items as oi')
                        ->join('order_product_variables as opv', 'opv.order_item_id', '=', 'oi.id')
                        ->whereIn('oi.order_id', $ocIds)                         // ordini cliente collegati
                        ->whereIn('opv.resolved_component_id', $compIds)         // componente variabile risolto
                        ->whereNotNull('opv.color_notes')
                        ->where('opv.color_notes', '<>', '')
                        ->select([
                            'oi.order_id as oc_id',
                            'opv.resolved_component_id as component_id',
                            'opv.color_notes',
                        ])
                        ->get();

                    $bucket = []; // key => array note
                    foreach ($rows as $r) {
                        $key = (int) $r->oc_id . ':' . (int) $r->component_id;
                        $bucket[$key] = $bucket[$key] ?? [];
                        $bucket[$key][] = trim((string) $r->color_notes);
                    }

                    foreach ($bucket as $k => $arr) {
                        $uniq = array_values(array_unique(array_filter($arr)));
                        $notesMap[$k] = count($uniq) ? implode("\n", $uniq) : null;
                    }
                }
            }

            /*──────────────── Trasforma righe per il front-end ────────────────*/
            $lines = $order->items->map(function ($it) use ($notesMap) {

                // Key per recuperare la nota dall’ordine cliente
                $ocId = (int) ($it->generated_by_order_customer_id ?? 0);
                $cid  = (int) ($it->component_id ?? 0);
                $key  = ($ocId > 0 && $cid > 0) ? ($ocId . ':' . $cid) : null;

                return [
                    'id' => $it->id,

                    'component' => [
                        'id'             => $it->component->id,
                        'code'           => $it->component->code,
                        'description'    => $it->component->description,
                        'unit_of_measure'=> $it->component->unit_of_measure,
                    ],

                    'qty'            => $it->quantity,
                    'unit_of_measure'=> $it->component->unit_of_measure,
                    'last_cost'      => $it->unit_price,
                    'subtotal'       => $it->quantity * $it->unit_price,

                    // NEW: note colori (vive sull’ordine cliente)
                    'color_notes'    => $key ? ($notesMap[$key] ?? null) : null,
                ];
            })->values();

            return response()->json([
                'id'              => $order->id,
                'order_number_id' => $order->order_number_id,
                'order_number'    => $order->orderNumber->number,
                'supplier'        => $order->supplier,
                'delivery_date'   => $order->delivery_date->format('Y-m-d'),
                'lines'           => $lines,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Ordine non trovato'], 404);

        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Errore interno'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(){
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Aggiorna un ordine fornitore e le sue righe.
     * 
     * @param  \Illuminate\Http\Request  $req
     * @param  \App\Models\Order         $order
     */
    public function update(Request $req, Order $order)
    {
        $data = $req->validate([
            'delivery_date'              => ['required','date'],
            'lines'                      => ['required','array','min:1'],
            'lines.*.id'                 => ['nullable','exists:order_items,id'],
            'lines.*.component_id'       => ['required','distinct','exists:components,id'],
            'lines.*.quantity'           => ['required','numeric','min:0.01'],
            'lines.*.last_cost'          => ['required','numeric','min:0'],

            // NEW: note colore (vive sull’ordine cliente)
            'lines.*.color_notes'        => ['nullable','string'],
        ],
        [
            'delivery_date.required' => 'La data di consegna è obbligatoria.',
            'lines.required'         => 'Le righe sono obbligatorie.',
            'lines.array'            => 'Le righe devono essere un array.',
            'lines.min'              => 'Devi fornire almeno una riga.',
            'lines.*.component_id.required' => 'Il componente è obbligatorio.',
            'lines.*.component_id.distinct' => 'Il componente deve essere unico.',
            'lines.*.component_id.exists'   => 'Il componente selezionato non è valido.',
        ]);

        /* ── Verifica che nel "set finale" (DB + input) non restino duplicati ─ */
        $orderId = $order->id;

        // FIX: qui usavi $request ma il parametro è $req
        $incoming = collect($req->input('lines', []))
            ->map(fn ($row) => (int) ($row['component_id'] ?? 0))
            ->filter()
            ->values()
            ->all();

        $excludeIds = collect($req->input('lines', []))
            ->map(fn ($row) => (int) ($row['id'] ?? 0))
            ->filter()
            ->values()
            ->all();

        $already = OrderItem::query()
            ->where('order_id', $orderId)
            ->when(!empty($excludeIds), fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->pluck('component_id')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values()
            ->all();

        $final = array_merge($already, $incoming);
        $dups  = collect($final)->countBy()->filter(fn ($c) => $c > 1)->keys();

        if ($dups->isNotEmpty()) {
            return response()->json([
                'message'    => 'Questo ordine contiene più righe per lo stesso componente.',
                'duplicates' => $dups->values(),
            ], 422);
        }

        DB::transaction(function () use ($order, $data) {

            /* ── aggiornamento header ── */
            $order->update([
                'delivery_date' => $data['delivery_date'],
            ]);

            /* ── sincronizzazione righe ── */
            $keepIds = [];
            $total   = 0;

            foreach ($data['lines'] as $row) {

                $subtotal = $row['quantity'] * $row['last_cost'];
                $total   += $subtotal;

                /** @var \App\Models\OrderItem $item */
                $item = $order->items()->updateOrCreate(
                    ['id' => $row['id'] ?? 0],
                    [
                        'component_id' => $row['component_id'],
                        'quantity'     => $row['quantity'],
                        'unit_price'   => $row['last_cost'],
                    ]
                );

                $keepIds[] = $item->id;

                /*──────────────── NEW: sync note colori sull’ordine cliente ────────────────*/
                $this->syncCustomerColorNotesFromSupplierItem(
                    supplierItem: $item,
                    noteRaw: $row['color_notes'] ?? null
                );
            }

            // elimina righe rimosse dall’utente
            $order->items()->whereNotIn('id', $keepIds)->delete();

            // aggiorna il totale
            $order->update(['total' => $total]);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Salva bolla e, se occorre, genera l’eventuale ordine “short-fall”.
     *
     * @param  \Illuminate\Http\Request        $request
     * @param  \App\Models\Order               $order          Ordine che si sta chiudendo
     * @param  \App\Services\ShortfallService  $svc            Service che crea l’ordine mancante
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRegistration(
        Request           $request,
        Order             $order,
        ShortfallService  $svc
    ): JsonResponse {

        /* ──────────────────────────────────────────────────────────────
        | 1‧ Validazione campi header ( data bolla + n° DDT )
        ────────────────────────────────────────────────────────────── */
        $data = $request->validate(
            [
                'delivery_date' => 'required|date|before_or_equal:today',
                'bill_number'   => 'required|string|max:50',
                'skip_shortfall' => ['sometimes','boolean'],
            ],
            [
                'delivery_date.required' => 'La data di consegna è obbligatoria.',
                'delivery_date.date'     => 'La data di consegna deve essere una data valida.',
                'delivery_date.before_or_equal'    => 'La data di consegna non può essere successiva ad oggi.',
                'bill_number.required'   => 'Il numero bolla è obbligatorio.',
            ]
        );

        /* ──────────────────────────────────────────────────────────────
        | 2‧ Aggiorna header dell’ordine
        ────────────────────────────────────────────────────────────── */
        $order->fill([
            'delivery_date'     => $data['delivery_date'],
            'registration_date' => $data['delivery_date'],
            'bill_number'       => $data['bill_number'],
        ])->save();

        if ($data['skip_shortfall'] == true) {
            return response()->json([
                'success'            => true,
                'skipped'            => true,      // info per il front-end
                'order'              => $order->only(['registration_date','bill_number']),
                'follow_up_order_id' => null,
                'follow_up_number'   => null,
            ]);
        }

        /* ──────────────────────────────────────────────────────────────
        | 3‧ Gestione short-fall (nuovo o nessuno)
        |────────────────────────────────────────────────────────────── */
        $parent = $order->load(['items.component', 'stockLevelLots.stockLevel']);

        /* 3-a  qty ricevute per componente */
        $received = $parent->stockLevelLots
            ->groupBy(fn ($lot) => $lot->stockLevel->component_id)
            ->map(fn ($g) => $g->sum('quantity'));

        /* 3-b  gap per riga ordine */
        $gaps = collect();
        foreach ($parent->items as $item) {
            $missing = $item->quantity - $received->get($item->component_id, 0);
            if ($missing > 0) {
                $gaps->push($item->id);
            }
        }

        /* 3-c  rimuovi gap già tracciati */
        $gaps = $gaps->reject(function ($orderItemId) {
            return OrderItemShortfall::where('order_item_id', $orderItemId)->exists();
        });

        /* 3-d  se non rimane nulla → nessun nuovo short-fall */
        if ($gaps->isEmpty()) {
            return response()->json([
                'success'            => true,
                'skipped'            => false,
                'order'              => $parent->only(['registration_date','bill_number']),
                'follow_up_order_id' => null,            // <-- front-end capirà che NON è stato creato
                'follow_up_number'   => null,
            ]);
        }

        /* 3-e  Crea eventualmente short-fall (gruppato per lead-time + permessi) */
        try {
            $canCreate = $request->user()->can('orders.supplier.create');
            $result    = $svc->captureGrouped($parent, $canCreate);
        } catch (AuthorizationException $e) {
            // Non bloccare il flusso: rispondi come “non creato per permessi”
            $result = [
                'needed'  => true,
                'created' => false,
                'blocked' => 'no_permission',
                'orders'  => [],
                'groups'  => [],
            ];
        }

        /* compat con il front-end esistente (mostra il primo se serve) */
        $first = $result['orders'][0] ?? null;

        return response()->json([
            'success'             => true,
            'skipped'             => false,
            'order'               => $parent->only(['registration_date','bill_number']),
            'follow_up_order_id'  => $first['id']     ?? null,
            'follow_up_number'    => $first['number'] ?? null,
            'shortfall_needed'    => $result['needed'],
            'shortfall_created'   => $result['created'],
            'shortfall_blocked'   => $result['blocked'],       // <- 'no_permission' se manca permesso
            'follow_up_orders'    => $result['orders'],        // tutti quelli creati
            'groups'              => $result['groups'],
        ]);
    }

    /**
     * Elimina un ordine fornitore e le sue righe.
     * – pulisce le righe in order_items
     * – rimuove la voce nel registro order_numbers
     * 
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Order $order): RedirectResponse
    {
        // sicurezza: deve essere davvero un ordine fornitore
        if ($order->orderNumber->order_type !== 'supplier') {
            abort(403, 'Operazione non consentita');
        }

        DB::transaction(function () use ($order) {

            // elimina righe (FK cascade OK, ma esplicito per chiarezza)
            $order->items()->delete();

            // elimina l’ordine
            $order->delete();

            // elimina la riga nel registro (opzionale; se vuoi conservarla, commenta)
            $order->orderNumber()->delete();
        });

        return back()->with('success', 'Ordine eliminato con successo.');
    }

    /**
     * NEW: Aggiorna order_product_variables.color_notes sull’ordine cliente collegato
     * partendo da una riga PO.
     *
     * Match:
     * - OC = order_items.generated_by_order_customer_id (sulla riga PO)
     * - componente variabile = order_product_variables.resolved_component_id == order_items.component_id (PO)
     *
     * Se più righe OC matchano lo stesso componente, aggiorna tutte (coerente con nota “di commessa”).
     */
    private function syncCustomerColorNotesFromSupplierItem(OrderItem $supplierItem, ?string $noteRaw): void
    {
        // Safety: se la colonna non esiste in qualche ambiente, non facciamo nulla
        if (!Schema::hasColumn('order_product_variables', 'color_notes')) {
            return;
        }

        $ocId        = (int) ($supplierItem->generated_by_order_customer_id ?? 0);
        $componentId = (int) ($supplierItem->component_id ?? 0);

        // Se la riga PO non è collegata a un ordine cliente, non possiamo sincronizzare
        if ($ocId <= 0 || $componentId <= 0) {
            return;
        }

        // Normalizza: stringa vuota -> null
        $note = trim((string) $noteRaw);
        $note = $note !== '' ? $note : null;

        // Aggiorna tutte le variabili delle righe cliente che risolvono quel componente
        DB::table('order_product_variables as opv')
            ->join('order_items as oi', 'oi.id', '=', 'opv.order_item_id')
            ->where('oi.order_id', $ocId)
            ->where('opv.resolved_component_id', $componentId)
            ->update([
                'opv.color_notes' => $note,
            ]);
    }
}
