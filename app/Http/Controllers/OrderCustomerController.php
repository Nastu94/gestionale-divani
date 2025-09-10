<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNumber;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\StockMovement;
use App\Models\Customer;
use App\Models\OccasionalCustomer;
use App\Models\Product;
use App\Models\Fabric;
use App\Models\Color;
use App\Models\Component;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\OrderUpdateService;
use App\Services\OrderDeleteService;
use App\Services\Traits\InventoryServiceExtensions;
use App\Support\Pricing\CustomerPriceResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class OrderCustomerController extends Controller
{
    // inietta il resolver
    public function __construct(private CustomerPriceResolver $priceResolver) {}

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
        
        // Carico le opzioni globali Tessuti/Colori attivi (id + name o code come fallback)
        $fabrics = Fabric::query()
            ->when(\Schema::hasColumn('fabrics','is_active'), fn($q)=>$q->where('is_active',1))
            ->orderBy('name')
            ->get(['id','name','code']);

        $colors = Color::query()
            ->when(\Schema::hasColumn('colors','is_active'), fn($q)=>$q->where('is_active',1))
            ->orderBy('name')
            ->get(['id','name','code']);

        $variableOptions = [
            'fabrics' => $fabrics->map(fn($f)=>[
                'id'   => $f->id,
                'name' => $f->name ?? $f->code ?? ('Tessuto #'.$f->id),
            ])->values(),
            'colors'  => $colors->map(fn($c)=>[
                'id'   => $c->id,
                'name' => $c->name ?? $c->code ?? ('Colore #'.$c->id),
            ])->values(),
        ];

        return view('pages.orders.index-customers',
            compact('orders', 'sort', 'dir', 'filters'))
            ->with('variableOptions', $variableOptions);
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
     * • Prezzo unitario riga risolto server-side con CustomerPriceResolver
     *   (customer_id + product_id + delivery_date). Se l’utente possiede il
     *   permesso "orders.price.override" ed invia un prezzo, prevale l’override.
     * • Il prezzo viene sempre "congelato" sulla riga (storico coerente).
     * • Logica esistente (reservation/PO) invariata.
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('OrderCustomer@store – richiesta ricevuta (con variabili)', [
            'user_id' => optional($request->user())->id,
            'payload' => $request->all(),
        ]);

        /*─────────── VALIDAZIONE ───────────*/
        $data = $request->validate([
            'order_number_id'        => ['required', 'integer', Rule::exists('order_numbers', 'id')],
            'occasional_customer_id' => ['nullable', 'integer', Rule::exists('occasional_customers', 'id')],
            'customer_id'            => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'delivery_date'          => ['required', 'date'],
            'shipping_address'       => ['required', 'string', 'max:255'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.product_id'     => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.quantity'       => ['required', 'numeric', 'min:0.01'],

            // prezzo lato client opzionale (solo se l’utente ha permesso override)
            'lines.*.price'          => ['nullable', 'numeric', 'min:0'],

            // variabili di riga
            'lines.*.fabric_id'      => ['nullable', 'integer', Rule::exists('fabrics', 'id')],
            'lines.*.color_id'       => ['nullable', 'integer', Rule::exists('colors', 'id')],
        ]);

        Log::debug('OrderCustomer@store – dati validati', $data);

        /* esclusività customer_id / occasional_customer_id */
        if (! ($data['customer_id'] xor ($data['occasional_customer_id'] ?? null))) {
            Log::warning('OrderCustomer@store – violazione esclusività customer vs occasional', $data);
            return response()->json(['message' => 'Indicare solo customer_id oppure occasional_customer_id.'], 422);
        }

        $deliveryDate = Carbon::parse($data['delivery_date'])->toDateString();

        /*─────────── VERIFICA DISPONIBILITÀ (obbligatoria) ───────────*/
        $inv = InventoryService::forDelivery($deliveryDate)
            ->check(collect($data['lines'])->map(fn($l)=>[
                'product_id' => (int) $l['product_id'],
                'quantity'   => (float) $l['quantity'],
                'fabric_id'  => array_key_exists('fabric_id', $l) && $l['fabric_id'] !== null ? (int) $l['fabric_id'] : null, // NEW
                'color_id'   => array_key_exists('color_id',  $l) && $l['color_id']  !== null ? (int) $l['color_id']  : null, // NEW
            ])->values()->all());

        if ($inv === null) {
            return response()->json(['message'=>'Esegui prima la verifica disponibilità.'], 409);
        }

        /*─────────── PREPARAZIONE RIGHE (validazioni variabili + prezzi) ───────────*/
        $customerId  = $data['customer_id'] ?? null; // guest → null
        $canOverride = $request->user()->can('orders.price.override');

        // helper locali per gestire tipi/value dei sovrapprezzi e calcolare importi
        $normType = function (?string $t): string {
            $t = strtolower((string)$t);
            return in_array($t, ['percent','percentage','%'], true) ? 'percent' : 'fixed';
        };
        $appliedAmount = function (float $base, string $type, ?float $value, float &$fixedSum, float &$percentSum): float {
            $v = (float)($value ?? 0);
            if ($type === 'percent') { $percentSum += $v; return $base * ($v / 100); }
            $fixedSum += $v; return $v;
        };
        $resolveResolvedComponentId = function (Product $product, ?int $fabricId, ?int $colorId): ?int {
            $placeholder = $product->variableComponent();    // riga BOM “slot”
            if (! $placeholder) return null;
            $qid = Component::query()
                ->where('category_id', $placeholder->category_id)
                ->when($fabricId, fn($q)=>$q->where('fabric_id', $fabricId))
                ->when($colorId,  fn($q)=>$q->where('color_id',  $colorId))
                ->value('id');
            return $qid ?: $placeholder->id; // fallback prudente allo slot, se vuoi metti null
        };
        $fabricMeta = function (Product $product, ?int $fabricId): array {
            if (!$fabricId) return ['type'=>null,'value'=>null];
            $pf = $product->fabrics()->where('fabrics.id', $fabricId)->first();
            $type  = $pf?->pivot?->surcharge_type;
            $value = $pf?->pivot?->surcharge_value;
            if ($type === null || $value === null) { // fallback alla tabella fabrics
                $fab   = Fabric::select('surcharge_type','surcharge_value')->find($fabricId);
                $type  = $fab?->surcharge_type;
                $value = $fab?->surcharge_value;
            }
            return ['type'=>$type, 'value'=>$value];
        };
        $colorMeta = function (Product $product, ?int $colorId): array {
            if (!$colorId) return ['type'=>null,'value'=>null];
            $pc = $product->colors()->where('colors.id', $colorId)->first();
            $type  = $pc?->pivot?->surcharge_type;
            $value = $pc?->pivot?->surcharge_value;
            if ($type === null || $value === null) { // fallback alla tabella colors
                $col   = Color::select('surcharge_type','surcharge_value')->find($colorId);
                $type  = $col?->surcharge_type;
                $value = $col?->surcharge_value;
            }
            return ['type'=>$type, 'value'=>$value];
        };

        $resolvedLines = collect($data['lines'])->map(function (array $l) use (
            $customerId, $deliveryDate, $canOverride,
            $normType, $appliedAmount, $resolveResolvedComponentId, $fabricMeta, $colorMeta) {

            $productId = (int) $l['product_id'];
            $qty       = (float) $l['quantity'];
            $fabricId  = array_key_exists('fabric_id', $l) && $l['fabric_id'] !== null ? (int) $l['fabric_id'] : null;
            $colorId   = array_key_exists('color_id',  $l) && $l['color_id']  !== null ? (int) $l['color_id']  : null;

            /** @var \App\Models\Product $product */
            $product   = Product::findOrFail($productId);

            // ✅ Sicurezza: le variabili passate devono essere nella whitelist del prodotto
            if ($fabricId !== null && ! in_array($fabricId, $product->fabricIds(), true)) {
                abort(response()->json([
                    'message' => "Il tessuto selezionato non è consentito per il prodotto #{$productId}."
                ], 422));
            }
            if ($colorId !== null && ! in_array($colorId, $product->colorIds(), true)) {
                abort(response()->json([
                    'message' => "Il colore selezionato non è consentito per il prodotto #{$productId}."
                ], 422));
            }

            // Prezzo unitario
            if ($canOverride && array_key_exists('price', $l) && $l['price'] !== null && $l['price'] !== '') {
                $unit = (string) $l['price'];
                $base = (float) $product->effectiveBasePriceForDate($customerId, $deliveryDate); // per calcolare % applicate in modo coerente
                $breakdown = ['base_price'=>$base,'fabric_surcharge'=>0.0,'color_surcharge'=>0.0]; // placeholder
            } else {
                $breakdown = $product->pricingBreakdown($fabricId, $colorId, $customerId, $deliveryDate);
                $unit = (string) $breakdown['unit_price']; // decimale
            }

            // ── calcolo metadati sovrapprezzi da salvare (tipi+importi) ──  // NEW
            $base = (float) ($breakdown['base_price'] ?? $product->effectiveBasePriceForDate($customerId, $deliveryDate));

            $fixedSum   = 0.0;
            $percentSum = 0.0;

            $fm = $fabricMeta($product, $fabricId);
            $cm = $colorMeta($product,  $colorId);

            if ($fm['type'] !== null) {
                $fabricType = $normType($fm['type']);
                $fabricAmt  = $appliedAmount($base, $fabricType, (float)$fm['value'], $fixedSum, $percentSum);
            } else {
                $fabricType = null; $fabricAmt = 0.0;
            }
            if ($cm['type'] !== null) {
                $colorType  = $normType($cm['type']);
                $colorAmt   = $appliedAmount($base, $colorType, (float)$cm['value'], $fixedSum, $percentSum);
            } else {
                $colorType = null; $colorAmt = 0.0;
            }

            $surchargeTotalApplied = $fabricAmt + $colorAmt;

            // componente effettivo risolto (per lo slot variabile)                 // NEW
            $resolvedComponentId = $resolveResolvedComponentId($product, $fabricId, $colorId);

            Log::debug('OrderCustomer@store – pricing line resolved', [
                'product_id' => $productId,
                'qty'        => $qty,
                'fabric_id'  => $fabricId,
                'color_id'   => $colorId,
                'unit_price' => $unit,
                'base_price' => $base,
                'surch_fixed'=> $fixedSum,
                'surch_pct'  => $percentSum,
                'surch_tot'  => $surchargeTotalApplied,
                'resolved_component_id' => $resolvedComponentId,
            ]);

            return [
                'product_id' => $productId,
                'quantity'   => $qty,
                'price'      => $unit,     // string decimal
                'fabric_id'  => $fabricId,
                'color_id'   => $colorId,

                // NEW → persistiamo già i meta da passare alla transaction
                'resolved_component_id'     => $resolvedComponentId,
                'surcharge_fixed_applied'   => $fixedSum,
                'surcharge_percent_applied' => $percentSum,      // somma dei punti % applicati (es. 10 + 5 = 15)
                'surcharge_total_applied'   => $surchargeTotalApplied, // € complessivi dovuti ai sovrapprezzi
            ];
        })->values();

        // Totale ordine calcolato con i prezzi definitivi
        $total = $resolvedLines->reduce(fn(float $s, $l) => $s + ($l['quantity'] * (float) $l['price']), 0.0);

        try {
            /*─────────── TRANSAZIONE: salva ordine + righe + variabili ───────────*/
            $order = DB::transaction(function () use ($data, $deliveryDate, $resolvedLines, $total) {

                /* 1) blocca OrderNumber (customer) */
                $orderNumber = OrderNumber::where('id', $data['order_number_id'])
                    ->where('order_type', 'customer')
                    ->with('order')
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($orderNumber->order) {
                    abort(409, 'OrderNumber già assegnato.');
                }

                /* 2) header */
                /** @var \App\Models\Order $order */
                $order = Order::create([
                    'order_number_id'        => $orderNumber->id,
                    'customer_id'            => $data['customer_id']            ?? null,
                    'occasional_customer_id' => $data['occasional_customer_id'] ?? null,
                    'shipping_address'       => $data['shipping_address'],
                    'total'                  => $total,
                    'ordered_at'             => now(),
                    'delivery_date'          => $deliveryDate,
                ]);

                /* 3) righe + variabili */
                foreach ($resolvedLines as $line) {
                    /** @var \App\Models\OrderItem $item */
                    $item = $order->items()->create([
                        'product_id' => $line['product_id'],
                        'quantity'   => $line['quantity'],
                        'unit_price' => $line['price'],   // congelato
                    ]);

                    // Persisti le variabili della riga (se presenti)
                    $fabricId = $line['fabric_id'] ?? null;
                    $colorId  = $line['color_id']  ?? null;

                    if ($fabricId !== null || $colorId !== null) {

                        $payload = [];

                        if (Schema::hasColumn('order_product_variables', 'fabric_id'))  { $payload['fabric_id']  = $fabricId; }
                        if (Schema::hasColumn('order_product_variables', 'color_id'))   { $payload['color_id']   = $colorId; }
                        if (Schema::hasColumn('order_product_variables', 'resolved_component_id')) { // NEW
                            $payload['resolved_component_id'] = $line['resolved_component_id'] ?? null;
                        }
                        if (Schema::hasColumn('order_product_variables', 'surcharge_fixed_applied')) { // NEW
                            $payload['surcharge_fixed_applied'] = $line['surcharge_fixed_applied'] ?? 0;
                        }
                        if (Schema::hasColumn('order_product_variables', 'surcharge_percent_applied')) { // NEW
                            $payload['surcharge_percent_applied'] = $line['surcharge_percent_applied'] ?? 0;
                        }
                        if (Schema::hasColumn('order_product_variables', 'surcharge_total_applied')) { // NEW
                            $payload['surcharge_total_applied'] = $line['surcharge_total_applied'] ?? 0;
                        }

                        if (!empty($payload)) {
                            $item->variables()->create($payload);
                        }
                    }
                }

                /* 4) Prenotazioni stock */
                $usedLines = $resolvedLines->map(fn ($l) => [
                    'product_id' => $l['product_id'],
                    'quantity'   => $l['quantity'],
                    'fabric_id'  => $l['fabric_id'] ?? null,
                    'color_id'   => $l['color_id']  ?? null,
                ])->all();

                $invResult = InventoryService::forDelivery($deliveryDate, $order->id)->check($usedLines);

                InventoryServiceExtensions::reserveFreeIncoming(
                    $order,
                    $invResult->shortage->pluck('shortage', 'component_id')->toArray(),
                    Carbon::parse($deliveryDate)
                );

                $invResult = InventoryService::forDelivery($deliveryDate, $order->id)->check($usedLines);

                foreach ($invResult->shortage as $row) {
                    $missing    = $row['shortage'];
                    $fromStock = min($row['available'], $missing);

                    if ($fromStock > 0) {
                        $sl = StockLevel::where('component_id', $row['component_id'])
                            ->orderBy('quantity')
                            ->first();

                        StockReservation::create([
                            'stock_level_id' => $sl->id,
                            'order_id'       => $order->id,
                            'quantity'       => $fromStock,
                        ]);

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
                    'lines'    => $resolvedLines->count(),
                    'total'    => $total,
                ]);

                return $order;
            });

            /*─────────── CREA / MERGE PO se servono ───────────*/
            $poNumbers = [];
            if (! $inv->ok && $request->user()->can('orders.supplier.create')) {
                $shortCol = ProcurementService::buildShortageCollection($inv->shortage);
                $proc     = ProcurementService::fromShortage($shortCol, $order->id);
                $poNumbers= $proc['po_numbers']->all();
            }

            return response()->json([
                'order_id'   => $order->id,
                'po_numbers' => $poNumbers,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('OrderCustomer@store – eccezione', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 2048),
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
     * Coerenza con la nuova logica prezzi:
     * - Il prezzo unitario del prodotto proviene SEMPRE da $item->unit_price
     *   (valore “congelato” salvato dallo store/update tramite resolver),
     *   quindi NON ricalcoliamo nulla e non richiamiamo il resolver qui.
     * - I costi dei componenti restano informativi (come nel codice originale),
     *   stimati sul supplier col last_cost più basso, se presente.
     *
     * @param  Order  $order  (route model binding)
     * @return \Illuminate\Http\JsonResponse
     */
    public function lines(Order $order): JsonResponse
    {
        // 1) Eager-load: prodotto, BOM con pivot (quantity/is_variable/slot),
        //    suppliers dei componenti BOM, e la variabile di riga (hasOne "variable").
        $order->load([
            'items.product',                                // id, sku, name (ok anche senza select mirato)
            'items.product.components.componentSuppliers',  // per last_cost indicativo
            'items.product.components' => function ($q) {
                // Non stringiamo le colonne per non perdere i campi pivot.
                // La relazione Product::components ha già withPivot(['quantity','is_variable','variable_slot'])
            },
            'items.variable:id,order_item_id,fabric_id,color_id,resolved_component_id',
        ]);

        Log::debug('OrderCustomer@lines - lines for order', [
            'order_id' => $order->id,
            'lines'    => $order->items,
        ]);

        // 2) Pre-carica TUTTI i componenti risolti referenziati dalle righe ordine
        $resolvedIds = $order->items
            ->pluck('variable.resolved_component_id')
            ->filter()
            ->unique()
            ->values();

        Log::debug('OrderCustomer@lines - resolved component IDs', [
            'order_id' => $order->id,
            'resolved_ids' => $resolvedIds,
        ]);

        /** @var \Illuminate\Support\Collection<int,\App\Models\Component> $resolvedMap */
        $resolvedMap = \App\Models\Component::query()
            ->whereIn('id', $resolvedIds)
            ->with(['componentSuppliers:id,component_id,last_cost'])
            ->get()
            ->keyBy('id');

        Log::debug('OrderCustomer@lines - loaded resolved components', [
            'order_id' => $order->id,
            'components' => $resolvedMap->values(),
        ]);

        $rows = collect();

        foreach ($order->items as $item) {
            $product    = $item->product;
            $qtyOrdered = (float) $item->quantity;
            $priceUnit  = (float) $item->unit_price;
            $subtotal   = $qtyOrdered * $priceUnit;

            /* ───────────────── 1) Riga PRODOTTO ───────────────── */
            $rows->push([
                'code'   => $product->sku,
                'desc'   => $product->name,
                'qty'    => $qtyOrdered,
                'unit'   => 'pz',
                'price'  => $priceUnit,       // prezzo congelato di vendita
                'subtot' => $subtotal,
                'type'   => 'product',
            ]);

            Log::debug('OrderCustomer@lines - product row', [
                'order_id' => $order->id,
                'row'      => $rows->last(),
            ]);

            /* ─────────────── 2) Righe COMPONENTI ────────────────
            Se il componente BOM è "variabile", sostituisco il placeholder
            con il componente realmente selezionato in ordine (resolved_component_id).
            */
            $resolvedId = $item->variable?->resolved_component_id;

            foreach ($product->components as $component) {
                $isVar = (bool) ($component->pivot->is_variable ?? false);

                // Se variabile e ho un resolved_component_id valido → sostituisco
                $compEff = ($isVar && $resolvedId && $resolvedMap->has($resolvedId))
                    ? $resolvedMap->get($resolvedId)
                    : $component;

                // qty componente richiesto per la riga d’ordine
                $qtyComp = $qtyOrdered * (float) ($component->pivot->quantity ?? 0);

                // costo indicativo: supplier col last_cost più basso
                $priceComp = optional(
                    $compEff->componentSuppliers->sortBy('last_cost')->first()
                )->last_cost ?? 0.0;

                $rows->push([
                    'code'   => $compEff->code,
                    'desc'   => $compEff->description,
                    'qty'    => $qtyComp,
                    'unit'   => $compEff->unit_of_measure ?? 'pz',
                    'price'  => (float) $priceComp,
                    'subtot' => (float) $priceComp * $qtyComp,
                    'type'   => 'component',
                ]);
            }
        }

        Log::debug('OrderCustomer@lines - all rows', [
            'order_id' => $order->id,
            'rows'     => $rows,
        ]);

        // Manteniamo l’ordine "prodotto → suoi componenti"
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
            'items.variable:id,order_item_id,fabric_id,color_id,resolved_component_id,surcharge_fixed_applied,surcharge_percent_applied,surcharge_total_applied',
            'items.variable.fabric:id,name',
            'items.variable.color:id,name',
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

        Log::debug('OrderCustomer@edit - lines for order', [
            'order_id' => $order->id,
            'lines'    => $order->items,
        ]);

        return response()->json([
            'id'              => $order->id,
            'number'          => $order->number,
            'order_number_id' => $order->order_number_id,
            'delivery_date'   => $order->delivery_date->format('Y-m-d'),
            'customer'        => $cust,
            'occ_customer'    => $occ,
            'shipping_address'=> $order->shipping_address,
            'lines'           => $order->items->map(fn ($it) => [
                'product_id' => $it->product_id,
                'sku'        => $it->product->sku,
                'name'       => $it->product->name,
                'quantity'   => $it->quantity,
                'price'      => $it->unit_price,
                'fabric_id'  => $it->variable->fabric_id,
                'color_id'   => $it->variable->color_id,
                'fabric_name' => $it->variable?->fabric?->name,
                'color_name'  => $it->variable?->color?->name,
            ]),
        ]);
    }

    /**
     * Aggiorna un ordine cliente + righe.
     *
     * • Per le righe NUOVE o modificate, il prezzo viene risolto server-side
     *   con CustomerPriceResolver (salvo override con permesso dedicato).
     * • Le righe già esistenti mantengono il prezzo salvato, salvo vengano
     *   esplicitamente sostituite dal front-end (dipende da OrderUpdateService).
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        /*──────── VALIDAZIONE ────────*/
        $data = $request->validate([
            'delivery_date'      => ['required','date'],
            'lines'              => ['required','array','min:1'],

            // ❗ niente 'distinct': possiamo avere più righe stesso prodotto con variabili diverse
            'lines.*.product_id' => ['required','integer', Rule::exists('products','id')],
            'lines.*.quantity'   => ['required','numeric','min:0.01'],

            // prezzo opzionale (override)
            'lines.*.price'      => ['nullable','numeric','min:0'],

            // variabili
            'lines.*.fabric_id'  => ['nullable','integer', Rule::exists('fabrics','id')],
            'lines.*.color_id'   => ['nullable','integer', Rule::exists('colors','id')],
        ]);

        $customerId  = $order->customer_id; // guest → null
        $delivery    = Carbon::parse($data['delivery_date']);
        $canOverride = $request->user()->can('orders.price.override');

        /*──────── PREPARAZIONE RIGHE (prezzo + variabili + campi applicati) ────────*/
        $prepared = collect($data['lines'])->map(function(array $l) use ($customerId, $delivery, $canOverride) {
            $productId = (int) $l['product_id'];
            $qty       = (float) $l['quantity'];
            $fabricId  = array_key_exists('fabric_id', $l) && $l['fabric_id'] !== null ? (int) $l['fabric_id'] : null;
            $colorId   = array_key_exists('color_id',  $l) && $l['color_id']  !== null ? (int) $l['color_id']  : null;

            /** @var Product $product */
            $product = Product::findOrFail($productId);

            // whitelist sicurezza
            if ($fabricId !== null && ! in_array($fabricId, $product->fabricIds(), true)) {
                abort(response()->json(['message' => "Il tessuto selezionato non è consentito per il prodotto #{$productId}."], 422));
            }
            if ($colorId !== null && ! in_array($colorId, $product->colorIds(), true)) {
                abort(response()->json(['message' => "Il colore selezionato non è consentito per il prodotto #{$productId}."], 422));
            }

            // prezzo
            if ($canOverride && array_key_exists('price', $l) && $l['price'] !== null && $l['price'] !== '') {
                $unit = (string) $l['price'];
                $base = (float) ($product->effectiveBasePriceFor($customerId) ?? 0);
                $fb   = ['base_price'=>$base, 'fabric_surcharge'=>0.0, 'color_surcharge'=>0.0, 'unit_price'=>(float)$unit, 'base_source'=>'override'];
            } else {
                $fb = $product->pricingBreakdown($fabricId, $colorId, $customerId, $delivery->toDateString());
                if ($fb === null) {
                    abort(response()->json(['message' => 'Prezzo non disponibile per uno dei prodotti nella data indicata.'], 422));
                }
                $unit = (string) $fb['unit_price'];
            }

            // resolved_component_id (categoria dal placeholder variabile della BOM)
            $resolvedId = null;
            $placeholder = $product->components()
                ->wherePivot('is_variable', 1)->first();
            if ($placeholder && $fabricId && $colorId) {
                $q = Component::query()
                    ->where('fabric_id', $fabricId)
                    ->where('color_id',  $colorId);
                if (Schema::hasColumn('components','category_id') && $placeholder->category_id) {
                    $q->where('category_id', $placeholder->category_id);
                }
                $resolvedId = $q->value('id');
            }

            // sconti/sovrapprezzi applicati (come in store)
            $basePrice  = (float) ($fb['base_price'] ?? 0);
            $fDelta     = (float) ($fb['fabric_surcharge'] ?? 0);
            $cDelta     = (float) ($fb['color_surcharge']  ?? 0);
            $totalDelta = $fDelta + $cDelta;

            $fixedApplied   = 0.0;
            $percentApplied = 0.0;

            if ($fabricId) {
                $pf   = $product->fabrics()->where('fabrics.id', $fabricId)->first();
                $ft   = $pf?->pivot?->surcharge_type ?? Fabric::find($fabricId)?->surcharge_type;
                $fv   = $pf?->pivot?->surcharge_value ?? Fabric::find($fabricId)?->surcharge_value;
                if ($ft) {
                    if (in_array($ft, ['percent','percentage','%'])) $percentApplied += (float)$fv;
                    else                                            $fixedApplied   += (float)$fv;
                }
            }
            if ($colorId) {
                $pc   = $product->colors()->where('colors.id', $colorId)->first();
                $ct   = $pc?->pivot?->surcharge_type ?? Color::find($colorId)?->surcharge_type;
                $cv   = $pc?->pivot?->surcharge_value ?? Color::find($colorId)?->surcharge_value;
                if ($ct) {
                    if (in_array($ct, ['percent','percentage','%'])) $percentApplied += (float)$cv;
                    else                                            $fixedApplied   += (float)$cv;
                }
            }

            return [
                // chiave logica per il diff (prodotto+variabili)
                'key'        => sprintf('%d:%d:%d', $productId, $fabricId ?? 0, $colorId ?? 0),

                // dati riga
                'product_id' => $productId,
                'quantity'   => $qty,
                'price'      => $unit,

                // variabili
                'fabric_id'  => $fabricId,
                'color_id'   => $colorId,
                'resolved_component_id'   => $resolvedId,

                // applied surcharge (come nello store)
                'surcharge_fixed_applied'   => $fixedApplied,
                'surcharge_percent_applied' => $percentApplied,
                'surcharge_total_applied'   => $totalDelta,
            ];
        })->values();

        /*──────── BUSINESS LOGIC ────────*/
        $svc = app(OrderUpdateService::class);

        $result = $svc->handle(
            $order,
            $prepared,                        // ← include variabili + applied
            $delivery->toDateString()
        );

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
