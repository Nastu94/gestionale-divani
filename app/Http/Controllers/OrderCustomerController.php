<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNumber;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\OrderUpdateService;
use App\Services\OrderDeleteService;
use App\Services\Traits\InventoryServiceExtensions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderCustomerController extends Controller
{
    /**
     * Mostra l’elenco paginato degli ordini cliente.
     * Questo metodo gestisce la visualizzazione della pagina degli ordini,
     * con supporto per ordinamento e filtri dinamici.
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        /*──── Parametri ───*/
        $sort    = $request->input('sort', 'ordered_at');
        $dir     = $request->input('dir',  'desc') === 'asc' ? 'asc' : 'desc';
        $filters = $request->input('filter', []);

        /*──── Whitelist ordinabile ───*/
        $allowedSorts = ['id', 'customer', 'ordered_at', 'delivery_date', 'total'];
        if (! in_array($sort, $allowedSorts, true)) $sort = 'ordered_at';

        /*──── Query ───*/
        $orders = Order::query()
            ->with([
                'customer:id,company',
                'occasionalCustomer:id,company',
                'orderNumber:id,number,order_type'
            ])
            ->whereHas('orderNumber', fn ($q) => $q->where('order_type', 'customer'))

            /*──────── EXIST test produzione avviata ────────*/
            ->select('orders.*')                       // <- mantieni i campi base
            ->selectRaw(
                'EXISTS (
                    SELECT 1
                    FROM   order_items oi
                    JOIN   v_order_item_phase_qty v
                        ON v.order_item_id = oi.id
                    WHERE  oi.order_id    = orders.id
                    AND  v.phase        > 0          -- oltre “Inserito”
                    AND  v.qty_in_phase > 0
                ) AS has_started_prod'
            )

            /*─── Filtri ───*/
            ->when($filters['id'] ?? null,
                fn ($q,$v) => $q->where('id', $v))

            ->when($filters['customer'] ?? null, function ($q,$v) {
                $q->where(function ($q) use ($v) {
                    $q->whereHas('customer',
                            fn ($q) => $q->where('company','like',"%$v%"))
                    ->orWhereHas('occasionalCustomer',
                            fn ($q) => $q->where('company','like',"%$v%"));
                });
            })

            ->when($filters['ordered_at'] ?? null,
                fn ($q,$v) => $q->whereDate('ordered_at', $v))

            ->when($filters['delivery_date'] ?? null,
                fn ($q,$v) => $q->whereDate('delivery_date', $v))

            ->when($filters['total'] ?? null,
                fn ($q,$v) => $q->where('total', 'like', "%$v%"))

            /*─── Ordinamento ───*/
            ->when($sort === 'customer', function ($q) use ($dir) {
                $q->leftJoin('customers as c',          'orders.customer_id',            '=', 'c.id')
                ->leftJoin('occasional_customers as oc','orders.occasional_customer_id','=','oc.id')
                ->orderByRaw("COALESCE(c.company, oc.company) {$dir}")
                ->select('orders.*');
            }, function ($q) use ($sort, $dir) {
                $q->orderBy($sort, $dir);
            })

            ->paginate(15)
            ->appends($request->query());

        return view('pages.orders.index-customers',
            compact('orders', 'sort', 'dir', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Memorizza un nuovo ordine cliente.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('OrderCustomer@store – richiesta ricevuta', [
            'user_id' => optional($request->user())->id,
            'payload' => $request->all(),
        ]);

        /*─────────── VALIDAZIONE ───────────*/
        $data = $request->validate([
            'order_number_id'        => ['required', 'integer', Rule::exists('order_numbers', 'id')],
            'occasional_customer_id' => ['nullable', 'integer', Rule::exists('occasional_customers', 'id')],
            'customer_id'            => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'delivery_date'          => ['required', 'date'],
            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.product_id'     => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.quantity'       => ['required', 'numeric', 'min:0.01'],
            'lines.*.price'          => ['required', 'numeric', 'min:0'],
        ]);

        /* esclusività customer_id / occasional_customer_id */
        if (! ($data['customer_id'] xor $data['occasional_customer_id'] ?? null)) {
            Log::warning('OrderCustomer@store – violazione esclusività customer vs occasional', $data);
            return response()->json(['message' => 'Indicare solo customer_id oppure occasional_customer_id.'], 422);
        }

        /*─────────── VERIFICA DISPONIBILITÀ (obbligatoria) ───────────*/
        $inv = InventoryService::forDelivery($data['delivery_date'])
                ->check(collect($data['lines'])->map(fn($l)=>[
                    'product_id'=>$l['product_id'],
                    'quantity'  =>$l['quantity']
                ])->values()->all());

        if ($inv === null) {
            return response()->json(['message'=>'Esegui prima la verifica disponibilità.'], 409);
        }

        try {
            /*────── TRANSAZIONE: salva ordine + righe ──────*/
            $order = DB::transaction(function () use ($data) {

                /* 1. blocca OrderNumber customer */
                $orderNumber = OrderNumber::where('id', $data['order_number_id'])
                    ->where('order_type', 'customer')
                    ->with('order')
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($orderNumber->order) abort(409, 'OrderNumber già assegnato.');

                /* 2. totale */
                $total = collect($data['lines'])->reduce(
                    fn (float $s, $l) => $s + ($l['quantity'] * $l['price']),
                    0.0
                );

                /* 3. header */
                $order = Order::create([
                    'order_number_id'        => $orderNumber->id,
                    'customer_id'            => $data['customer_id']            ?? null,
                    'occasional_customer_id' => $data['occasional_customer_id'] ?? null,
                    'total'                  => $total,
                    'ordered_at'             => now(),
                    'delivery_date'          => $data['delivery_date'],
                ]);

                /* 4. righe */
                foreach ($data['lines'] as $line) {
                    $order->items()->create([
                        'product_id' => $line['product_id'],
                        'quantity'   => $line['quantity'],
                        'unit_price' => $line['price'],
                    ]);
                }

                // 5.1 Calcolo della disponibilità fisica usata (available)  
                $usedLines = collect($data['lines'])->map(fn ($l) => [
                    'product_id' => $l['product_id'],     // chiave corretta
                    'quantity'   => $l['quantity'],
                ])->all();

                $invResult = InventoryService::forDelivery(
                    $data['delivery_date'],
                    $order->id
                )->check($usedLines);

                // 🆕 5.2   prenota le quantità libere sui PO esistenti
                InventoryServiceExtensions::reserveFreeIncoming(
                    $order,
                    $invResult->shortage    // o, meglio, explodeBom($lines) per avere tutto il fabbisogno
                        ->pluck('needed', 'component_id')
                        ->toArray(),
                    Carbon::parse($data['delivery_date'])
                );

                // 5.3   ricalcola la situazione ***dopo*** la prenotazione appena fatta
                $invResult = InventoryService::forDelivery(
                    $data['delivery_date'], $order->id
                )->check($usedLines);

                // 5.4 Per ogni componente, prenota in stock_reservations  
                foreach ($invResult->shortage as $row) {
                    $needed    = $row['needed'];
                    $have      = $row['available'] + $row['incoming'] + $row['my_incoming'];
                    $fromStock = min($row['available'], $needed);  // quanto effettivamente prelevato

                    if ($fromStock > 0) {
                        // prendo uno StockLevel qualsiasi
                        $sl = StockLevel::where('component_id', $row['component_id'])
                                ->orderBy('quantity')
                                ->first();

                        // 5.4.1 crea prenotazione
                        StockReservation::create([
                            'stock_level_id' => $sl->id,
                            'order_id'       => $order->id,
                            'quantity'       => $fromStock,
                        ]);

                        // 5.4.2 registra movimento magazzino
                        StockMovement::create([
                            'stock_level_id' => $sl->id,
                            'type'           => 'reserve',
                            'quantity'       => $fromStock,
                            'note'           => "Prenotazione stock per OC #{$order->id}",
                        ]);
                    }
                }

                Log::info('OrderCustomer@store – ordine e righe salvati', [
                    'order_id' => $order->id,
                    'lines'    => count($data['lines']),
                ]);

                return $order;
            });

            /*─────────── CREA / MERGE PO + prenotazioni ───────────*/
            $poNumbers = [];
            if (! $inv->ok && $request->user()->can('orders.supplier.create')) {
                // 6.0 Costruisce la collezione di carenza
                $shortCol = ProcurementService::buildShortageCollection($inv->shortage);

                // 6.1 crea PO e prenotazioni
                $proc       = ProcurementService::fromShortage($shortCol, $order->id);
                $poNumbers  = $proc['po_numbers']->all();   // collection → array
            }

            return response()->json([
                'order_id' => $order->id,
                'po_numbers'   => $poNumbers,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('OrderCustomer@store – eccezione', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 1024),
            ]);

            return response()->json(['message' => 'Errore interno durante il salvataggio dell’ordine.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Restituisce in JSON le righe dell’ordine + esploso componenti.
     *
     * @param  Order  $order           Istanza iniettata tramite Route Model Binding
     * @return \Illuminate\Http\JsonResponse
     */
    public function lines(Order $order): JsonResponse
    {
        // carica i dati necessari in un solo round-trip
        $order->load([
            'items.product.components.componentSuppliers',  // per last_cost
        ]);

        $rows = collect();

        foreach ($order->items as $item) {
            $product    = $item->product;
            $qtyOrdered = $item->quantity;
            $priceUnit  = $item->unit_price;
            $subtotal   = $qtyOrdered * $priceUnit;

            /* ──────────────── 1) riga PRODOTTO ──────────────── */
            $rows->push([
                'code'   => $product->sku,
                'desc'   => $product->name,
                'qty'    => $qtyOrdered,
                'unit'   => 'pz',
                'price'  => $priceUnit,
                'subtot' => $subtotal,
                'type'   => 'product',
            ]);

            /* ─────────────── 2) righe COMPONENTI ────────────── */
            foreach ($product->components as $component) {
                // costo: pick del supplier con last_cost più basso o 0 se assente
                $priceComp = optional(
                    $component->componentSuppliers
                            ->sortBy('last_cost')
                            ->first()
                )->last_cost ?? 0;

                $rows->push([
                    'code'   => $component->code,
                    'desc'   => $component->description,
                    'qty'    => $qtyOrdered * $component->pivot->quantity,
                    'unit'   => $component->unit_of_measure ?? 'pz',
                    'price'  => $priceComp,
                    'subtot' => $priceComp * $qtyOrdered * $component->pivot->quantity,
                    'type'   => 'component',
                ]);
            }
        }

        // 🛈 NON ordiniamo: la sequenza è già «prodotto → componenti»
        return response()->json($rows->values());
    }

    /**
     * GET /orders/customer/{order}/edit
     * Restituisce i dati ordine in JSON per il modal “Modifica”.
     *
     * @param  Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Order $order): JsonResponse
    {
        /* ── Eager-load con i campi che servono alla UI ── */
        $order->load([
            'items.product:id,sku,name,price',
            'customer:id,company,email,vat_number,tax_code',
            'customer.shippingAddress:id,customer_id,address,city,postal_code,country',
            'occasionalCustomer:id,company,email,vat_number,tax_code,address,postal_code,city,province,country',
        ]);

        /* 2️⃣ helper per formattare l’indirizzo */
        $fmt = function ($addr = null) {
            if (!$addr) return null;

            return collect([
                $addr->address,
                $addr->postal_code && $addr->city ? "{$addr->postal_code} {$addr->city}" : $addr->city,
                $addr->province,
                $addr->country,
            ])->filter()->join(', ');
        };

        /* 3️⃣ serializza cliente “standard” */
        $cust = $order->customer ? [
            'id'              => $order->customer->id,
            'company'         => $order->customer->company,
            'email'           => $order->customer->email,
            'vat_number'      => $order->customer->vat_number,
            'tax_code'        => $order->customer->tax_code,
            'shipping_address'=> $fmt($order->customer->shippingAddress),
        ] : null;

        /* 4️⃣ serializza cliente occasionale */
        $occ  = $order->occasionalCustomer ? [
            'id'              => $order->occasionalCustomer->id,
            'company'         => $order->occasionalCustomer->company,
            'email'           => $order->occasionalCustomer->email,
            'vat_number'      => $order->occasionalCustomer->vat_number,
            'tax_code'        => $order->occasionalCustomer->tax_code,
            'shipping_address'=> $fmt($order->occasionalCustomer),   // stesso record
        ] : null;

        return response()->json([
            'id'              => $order->id,
            'number'          => $order->number,
            'order_number_id' => $order->order_number_id,
            'delivery_date'   => $order->delivery_date->format('Y-m-d'),
            'customer'        => $cust,
            'occ_customer'    => $occ,
            'lines'           => $order->items->map(fn ($it) => [
                'product_id' => $it->product_id,
                'sku'        => $it->product->sku,
                'name'       => $it->product->name,
                'quantity'   => $it->quantity,
                'price'      => $it->unit_price,
            ]),
        ]);
    }

    /**
     * Aggiorna un ordine cliente + righe.
     * 
     * @param  Request  $request
     * @param  Order  $order           Istanza iniettata tramite Route Model Binding
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        /*──────── VALIDAZIONE ────────*/
        $data = $request->validate([
            'delivery_date'      => ['required','date'],
            'lines'              => ['required','array','min:1'],
            'lines.*.product_id' => ['required','integer','distinct', Rule::exists('products','id')],
            'lines.*.quantity'   => ['required','numeric','min:0.01'],
            'lines.*.price'      => ['required','numeric','min:0'],
        ]);

        /*──────── BUSINESS LOGIC ────────*/
        $svc    = app(OrderUpdateService::class);

        $result = $svc->handle(
            $order,                         // ordine da modificare
            collect($data['lines']),        // righe in arrivo dal front-end
            $data['delivery_date']          // nuova data di consegna (o la stessa)
        );

        /*──────── RESPONSE ────────*/
        return response()->json($result);
    }

    /**
     * Elimina un ordine cliente e restituisce i numeri dei PO generati.
     */
    public function destroy(Order $order)
    {
        // già protetto dal middleware permission:orders.customer.delete

        /* ❗ eventuale business-rule: non eliminare se spedito / fatturato
        if ($order->shipped_at || $order->invoiced_at) {
            return back()->with('error',
                "Impossibile eliminare: l’ordine è già evaso / fatturato.");
        }
        */

        try {
            $poNumbers = app(OrderDeleteService::class)->handle($order);

            $msg = "Ordine #{$order->orderNumber->number} eliminato con successo.";
            if ($poNumbers->isNotEmpty()) {
                $msg .= ' Restano aperti gli ordini fornitore generati con la creazione dell\'ordine: '.
                        $poNumbers->implode(', ');
            }

            return back()->with('success', $msg);

        } catch (\Throwable $e) {
            Log::error('OC delete failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage()
            ]);

            return back()->with('error',
                'Errore interno: ordine non eliminato.');
        }
    }

}
