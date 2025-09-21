<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNumber;
use App\Models\Product;
use App\Models\Component;
use App\Models\Fabric;
use App\Models\Color;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\Customer;
use App\Models\OccasionalCustomer;
use App\Policies\OrderPolicy;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\OrderUpdateService;
use App\Services\OrderDeleteService;
use App\Services\Traits\InventoryServiceExtensions;
use App\Support\Pricing\CustomerPriceResolver;
use App\Mail\Orders\OrderConfirmationRequestMail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
     * Salva un nuovo ordine cliente.
     *
     * Regole:
     * - OCCASIONALE: conferma automatica (status=1) e si esegue SUBITO il flusso attuale:
     *   verifica → prenota → PO per shortfall. La "verifica disponibilità" lato FE è obbligatoria.
     * - STANDARD: allo store NON si toccano materiali/PO. Si genera un token di conferma e si invierà la mail
     *   (Step 4). I PO saranno valutati SOLO dopo conferma cliente e SOLO se delivery_date - confirmed_at < 30 gg.
     */
    public function store(Request $request): JsonResponse
    {
        Log::info('OrderCustomer@store – richiesta ricevuta (con variabili + sconti)', [
            'user_id' => optional($request->user())->id,
            'payload' => $request->all(),
        ]);

        /*──────────────── VALIDAZIONE ────────────────*/
        $data = $request->validate([
            'order_number_id'        => ['required', 'integer', Rule::exists('order_numbers', 'id')],
            'occasional_customer_id' => ['nullable', 'integer', Rule::exists('occasional_customers', 'id')],
            'customer_id'            => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'delivery_date'          => ['required', 'date'],
            'shipping_address'       => ['required', 'string', 'max:255'],

            // NEW: flag "#" (ordine nero) e note ordine
            'hash_flag'              => ['sometimes', 'boolean'],
            'note'                   => ['nullable', 'string'],

            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.product_id'     => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.quantity'       => ['required', 'numeric', 'min:0.01'],

            // prezzo lato client opzionale (solo se l’utente ha permesso override)
            'lines.*.price'          => ['nullable', 'numeric', 'min:0'],

            // variabili di riga
            'lines.*.fabric_id'      => ['nullable', 'integer', Rule::exists('fabrics', 'id')],
            'lines.*.color_id'       => ['nullable', 'integer', Rule::exists('colors', 'id')],

            // NEW: sconti riga come token "N%" o "N"
            'lines.*.discount'       => ['sometimes', 'array'],
            'lines.*.discount.*'     => ['string','regex:/^\d+(\.\d+)?%?$/'],
        ]);

        Log::debug('OrderCustomer@store – dati validati', $data);

        /* esclusività customer_id / occasional_customer_id */
        if (! ($data['customer_id'] xor ($data['occasional_customer_id'] ?? null))) {
            Log::warning('OrderCustomer@store – violazione esclusività customer vs occasional', $data);
            return response()->json(['message' => 'Indicare solo customer_id oppure occasional_customer_id.'], 422);
        }

        $deliveryDate = Carbon::parse($data['delivery_date'])->toDateString();
        $isOccasional = !empty($data['occasional_customer_id']); // NEW: ramo cliente occasionale

        /*──────────────── HELPER LOCALI (closure) ────────────────*/

        // Normalizza tipo surcharge (per logging/meta).
        $normType = function (?string $t): string {
            $t = strtolower((string)$t);
            return in_array($t, ['percent','percentage','%'], true) ? 'percent' : 'fixed';
        };

        // Somma importi surcharge per meta (solo diagnostica/meta, NON influenza il prezzo).
        $appliedAmount = function (float $base, string $type, ?float $value, float &$fixedSum, float &$percentSum): float {
            $v = (float)($value ?? 0);
            if ($type === 'percent') { $percentSum += $v; return $base * ($v / 100); }
            $fixedSum += $v; return $v;
        };

        // Risolve il componente effettivo dello slot variabile (per meta di tracciabilità).
        $resolveResolvedComponentId = function (Product $product, ?int $fabricId, ?int $colorId): ?int {
            $placeholder = $product->variableComponent();    // riga BOM “slot”
            if (! $placeholder) return null;
            $qid = Component::query()
                ->where('category_id', $placeholder->category_id)
                ->when($fabricId, fn($q)=>$q->where('fabric_id', $fabricId))
                ->when($colorId,  fn($q)=>$q->where('color_id',  $colorId))
                ->value('id');
            return $qid ?: $placeholder->id; // fallback prudente allo slot
        };

        // Meta surcharge tessuto/colore (prende pivot, fallback tabella).
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

        // applica i token sconto ("N%" o "N" in €) in sequenza sul prezzo lordo unitario.
        $applyDiscountTokens = function (float $unitGross, array $tokens): array {
            $price = $unitGross;
            foreach ($tokens as $tok) {
                if (!is_string($tok) || $tok === '') continue;
                $tok = trim($tok);
                if (str_ends_with($tok, '%')) {
                    $p = (float) substr($tok, 0, -1);
                    $price = $price * max(0.0, (100.0 - $p)) / 100.0;
                } else {
                    $f = (float) $tok;
                    $price = $price - max(0.0, $f);
                }
            }
            $unitNet = max(0.0, round($price, 2));
            $discVal = round($unitGross - $unitNet, 2);
            return [$unitNet, $discVal];
        };

        /*──────────────── VERIFICA DISPONIBILITÀ ────────────────*/
        // NEW: la pre-verifica (dry-run) è OBBLIGATORIA SOLO per gli OCCASIONALI.
        $inv = null;
        if ($isOccasional) {
            $inv = InventoryService::forDelivery($deliveryDate)
                ->check(collect($data['lines'])->map(fn($l)=>[
                    'product_id' => (int) $l['product_id'],
                    'quantity'   => (float) $l['quantity'],
                    'fabric_id'  => array_key_exists('fabric_id', $l) && $l['fabric_id'] !== null ? (int) $l['fabric_id'] : null,
                    'color_id'   => array_key_exists('color_id',  $l) && $l['color_id']  !== null ? (int) $l['color_id']  : null,
                ])->values()->all());

            if ($inv === null) {
                return response()->json(['message'=>'Esegui prima la verifica disponibilità.'], 409);
            }
        }

        /*──────────────── PREPARAZIONE RIGHE (variabili + pricing + sconti) ────────────────*/
        $customerId  = $data['customer_id'] ?? null; // guest → null
        $canOverride = $request->user()->can('orders.price.override');

        $resolvedLines = collect($data['lines'])->map(function (array $l) use (
            $customerId, $deliveryDate, $canOverride,
            $normType, $appliedAmount, $resolveResolvedComponentId, $fabricMeta, $colorMeta,
            $applyDiscountTokens
        ) {
            $productId = (int) $l['product_id'];
            $qty       = (float) $l['quantity'];
            $fabricId  = array_key_exists('fabric_id', $l) && $l['fabric_id'] !== null ? (int) $l['fabric_id'] : null;
            $colorId   = array_key_exists('color_id',  $l) && $l['color_id']  !== null ? (int) $l['color_id']  : null;

            /** @var \App\Models\Product $product */
            $product   = Product::findOrFail($productId);

            // Sicurezza: variabili scelte DEVONO essere in whitelist prodotto
            if ($fabricId !== null && ! in_array($fabricId, $product->fabricIds(), true)) {
                abort(response()->json(['message' => "Il tessuto selezionato non è consentito per il prodotto #{$productId}."], 422));
            }
            if ($colorId !== null && ! in_array($colorId, $product->colorIds(), true)) {
                abort(response()->json(['message' => "Il colore selezionato non è consentito per il prodotto #{$productId}."], 422));
            }

            // NEW: token sconto riga (array di stringhe "N%" o "N")
            $tokens = array_key_exists('discount', $l) && is_array($l['discount']) ? array_map('strval', $l['discount']) : [];

            // 1) Calcola SEMPRE il LORDO unitario (base + sovrapprezzi tessuto/colore)
            $breakdown = $product->pricingBreakdown($fabricId, $colorId, $customerId, $deliveryDate);
            $unitGross = (float) ($breakdown['unit_price'] ?? 0.0);

            // 2) NETTO unitario: override (se consentito) oppure applicazione sconti dopo i sovrapprezzi
            if ($canOverride && array_key_exists('price', $l) && $l['price'] !== null && $l['price'] !== '') {
                $unitNet  = (float) $l['price'];                // il valore passato È già il NETTO
                $discUnit = round($unitGross - $unitNet, 2);    // solo diagnostica
            } else {
                [$unitNet, $discUnit] = $applyDiscountTokens($unitGross, $tokens);
            }

            // 3) Meta surcharge (facoltativi, per tracciabilità)
            $base = (float) ($breakdown['base_price'] ?? $product->effectiveBasePriceFor($customerId, $deliveryDate));
            $fixedSum   = 0.0;
            $percentSum = 0.0;

            $fm = $fabricMeta($product, $fabricId);
            $cm = $colorMeta($product,  $colorId);

            if ($fm['type'] !== null) {
                $fabricType = $normType($fm['type']);
                $fabricAmt  = $appliedAmount($base, $fabricType, (float)$fm['value'], $fixedSum, $percentSum);
            } else { $fabricType = null; $fabricAmt = 0.0; }

            if ($cm['type'] !== null) {
                $colorType  = $normType($cm['type']);
                $colorAmt   = $appliedAmount($base, $colorType, (float)$cm['value'], $fixedSum, $percentSum);
            } else { $colorType = null; $colorAmt = 0.0; }

            $surchargeTotalApplied = $fabricAmt + $colorAmt;

            // componente effettivo risolto (per slot variabile)
            $resolvedComponentId = $resolveResolvedComponentId($product, $fabricId, $colorId);

            Log::debug('OrderCustomer@store – pricing line resolved (lordo/netto + meta)', [
                'product_id' => $productId,
                'qty'        => $qty,
                'fabric_id'  => $fabricId,
                'color_id'   => $colorId,
                'unit_gross' => $unitGross,
                'unit_net'   => $unitNet,
                'discount_tokens' => $tokens,
                'base_price' => $base,
                'surch_fixed'=> $fixedSum,
                'surch_pct'  => $percentSum,
                'surch_tot'  => $surchargeTotalApplied,
                'resolved_component_id' => $resolvedComponentId,
            ]);

            return [
                'product_id' => $productId,
                'quantity'   => $qty,

                // Persistiamo il NETTO come stringa decimale
                'price'      => number_format($unitNet, 2, '.', ''),

                'fabric_id'  => $fabricId,
                'color_id'   => $colorId,

                // token sconto da salvare su order_items.discount
                'discount'   => $tokens,

                // meta facoltativi (già presenti nella tua logica)
                'resolved_component_id'     => $resolvedComponentId,
                'surcharge_fixed_applied'   => $fixedSum,
                'surcharge_percent_applied' => $percentSum,
                'surcharge_total_applied'   => $surchargeTotalApplied,
            ];
        })->values();

        // Totale ordine calcolato sul NETTO (quantity * unit_price)
        $total = $resolvedLines->reduce(fn(float $s, $l) => $s + ($l['quantity'] * (float) $l['price']), 0.0);

        try {
            /*──────────────── TRANSAZIONE: salva ordine + righe + variabili ────────────────*/
            /** @var \App\Models\Order $order */
            $order = DB::transaction(function () use ($data, $deliveryDate, $resolvedLines, $total, $isOccasional) {

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
                $order = Order::create([
                    'order_number_id'        => $orderNumber->id,
                    'customer_id'            => $data['customer_id']            ?? null,
                    'occasional_customer_id' => $data['occasional_customer_id'] ?? null,
                    'shipping_address'       => $data['shipping_address'],
                    'total'                  => $total,
                    'ordered_at'             => now(),
                    'delivery_date'          => $deliveryDate,

                    // campi header aggiunti
                    'hash_flag'              => (bool) ($data['hash_flag'] ?? false),
                    'note'                   => $data['note'] ?? null,
                ]);

                /* 3) righe + variabili */
                foreach ($resolvedLines as $line) {
                    /** @var \App\Models\OrderItem $item */
                    $item = $order->items()->create([
                        'product_id' => $line['product_id'],
                        'quantity'   => $line['quantity'],
                        'unit_price' => $line['price'],           // NETTO congelato
                        'discount'   => $line['discount'] ?? [],  // token sconto in JSON
                    ]);

                    // Persisti le variabili della riga (se presenti)
                    $fabricId = $line['fabric_id'] ?? null;
                    $colorId  = $line['color_id']  ?? null;

                    if ($fabricId !== null || $colorId !== null) {
                        $payload = [];
                        if (Schema::hasColumn('order_product_variables', 'fabric_id'))  { $payload['fabric_id']  = $fabricId; }
                        if (Schema::hasColumn('order_product_variables', 'color_id'))   { $payload['color_id']   = $colorId; }
                        if (Schema::hasColumn('order_product_variables', 'resolved_component_id')) {
                            $payload['resolved_component_id'] = $line['resolved_component_id'] ?? null;
                        }
                        if (Schema::hasColumn('order_product_variables', 'surcharge_fixed_applied')) {
                            $payload['surcharge_fixed_applied'] = $line['surcharge_fixed_applied'] ?? 0;
                        }
                        if (Schema::hasColumn('order_product_variables', 'surcharge_percent_applied')) {
                            $payload['surcharge_percent_applied'] = $line['surcharge_percent_applied'] ?? 0;
                        }
                        if (Schema::hasColumn('order_product_variables', 'surcharge_total_applied')) {
                            $payload['surcharge_total_applied'] = $line['surcharge_total_applied'] ?? 0;
                        }

                        if (!empty($payload)) {
                            $item->variables()->create($payload);
                        }
                    }
                }

                /* 3.bis) gestione stato per tipo cliente */
                if ($isOccasional) {
                    // 🟢 OCCASIONALE → conferma automatica
                    if ((int) $order->status === 0) {
                        $order->status       = 1;
                        $order->confirmed_at = now();
                        $order->reason       = null;
                        $order->save();
                    }
                } else {
                    // 🔵 STANDARD → prepara token e marca richiesta (niente Response qui!)
                    $order->confirm_token             = (string) Str::uuid();
                    $order->confirmation_requested_at = now();
                    $order->confirm_locale            = app()->getLocale();
                    $order->save();
                }

                return $order; // IMPORTANT: restituiamo il Model, non una Response
            });

            // === Branching post-transazione ===

            if (is_null($order->occasional_customer_id)) {
                // Invia mail di conferma con token (code + locale)
                Mail::to($order->customer->email)->queue(new OrderConfirmationRequestMail(
                    order: $order,
                    replacePrevious: $isUpdate ?? false // true nell'update standard non confermato
                ));
                return response()->json([
                    'order_id'   => $order->id,
                    'po_numbers' => [],
                    'awaiting_confirmation'  => true,
                    'message'    => 'Richiesta conferma inviata al cliente.',
                ], 201);
            }

            // OCCASIONALI: prosegue il TUO flusso attuale (explode BOM → reserve → check → PO)

            $usedLines = $resolvedLines->map(fn ($l) => [
                'product_id' => $l['product_id'],
                'quantity'   => $l['quantity'],
                'fabric_id'  => $l['fabric_id'] ?? null,
                'color_id'   => $l['color_id']  ?? null,
            ])->all();

            // FABbisogno per componente (senza check iniziale)
            $componentsNeeded = InventoryServiceExtensions::explodeBomArray($usedLines);

            // Prenota arrivi liberi
            InventoryServiceExtensions::reserveFreeIncoming(
                $order,
                $componentsNeeded,
                Carbon::parse($deliveryDate)
            );

            // Check finale per shortfall residui
            $invResult = InventoryService::forDelivery($deliveryDate, $order->id)->check($usedLines);

            foreach ($invResult->shortage as $row) {
                $missing  = $row['shortage'];
                $fromStock= min($row['available'], $missing);

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

            /*────────── CREA / MERGE PO se servono (SOLO OCCASIONALI) ──────────*/
            $poNumbers = [];
            if ($inv && !$inv->ok && $request->user()->can('orders.supplier.create')) {
                $shortCol  = ProcurementService::buildShortageCollection($inv->shortage);
                $proc      = ProcurementService::fromShortage($shortCol, $order->id);
                $poNumbers = $proc['po_numbers']->all();
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
        return response()->json([
            'rows'   => $rows->values(),
            'note'   => $order->note,      // TEXT/nullable
            'reason' => $order->reason,    // TEXT/nullable → popolata in caso di rifiuto
        ]);
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

        /* 1️⃣ formatter indirizzo di spedizione */
        $fmt = function ($addr = null) {
            if (!$addr) return null;

            return collect([
                $addr->address,
                $addr->postal_code && $addr->city ? "{$addr->postal_code} {$addr->city}" : $addr->city,
                $addr->province,
                $addr->country,
            ])->filter()->join(', ');
        };

        /* 2️⃣ serializza cliente “standard” */
        $cust = $order->customer ? [
            'id'               => $order->customer->id,
            'company'          => $order->customer->company,
            'email'            => $order->customer->email,
            'vat_number'       => $order->customer->vat_number,
            'tax_code'         => $order->customer->tax_code,
            'shipping_address' => $fmt($order->customer->shippingAddress),
        ] : null;

        /* 3️⃣ serializza cliente occasionale */
        $occ  = $order->occasionalCustomer ? [
            'id'               => $order->occasionalCustomer->id,
            'company'          => $order->occasionalCustomer->company,
            'email'            => $order->occasionalCustomer->email,
            'vat_number'       => $order->occasionalCustomer->vat_number,
            'tax_code'         => $order->occasionalCustomer->tax_code,
            'shipping_address' => $fmt($order->occasionalCustomer),   // stesso record
        ] : null;

        Log::debug('OrderCustomer@edit - lines for order', [
            'order_id' => $order->id,
            'lines'    => $order->items,
        ]);

        /* 4️⃣ serializza righe:
        *    - price = unit_price NETTO salvato
        *    - discount = array token ("N%" | "N") → se nel DB è TEXT JSON, normalizzo
        *    - variabili con null-safety (->variable?->…)
        */
        $lines = $order->items->map(function ($it) {
            // Normalizza tokens sconto qualunque sia il cast del model
            $raw = $it->discount;
            if (is_array($raw)) {
                $tokens = array_values(array_map('strval', $raw));
            } elseif (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $tokens  = is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
            } else {
                $tokens = [];
            }

            return [
                'product_id'  => $it->product_id,
                'sku'         => $it->product?->sku,
                'name'        => $it->product?->name,
                'quantity'    => $it->quantity,
                'price'       => $it->unit_price,              // NETTO congelato
                'discount'    => $tokens,                      // ← necessario per editLine

                // Variabili (null-safe)
                'fabric_id'   => $it->variable?->fabric_id,
                'color_id'    => $it->variable?->color_id,
                'fabric_name' => $it->variable?->fabric?->name,
                'color_name'  => $it->variable?->color?->name,
            ];
        })->values();

        /* 5️⃣ risposta JSON per fetchOrder */
        return response()->json([
            'id'               => $order->id,
            'number'           => $order->number,
            'order_number_id'  => $order->order_number_id,
            'delivery_date'    => $order->delivery_date?->format('Y-m-d'),
            'customer'         => $cust,
            'occ_customer'     => $occ,
            'shipping_address' => $order->shipping_address,

            // NEW: campi header aggiunti/attesi dalla UI
            'hash_flag'        => (bool) ($order->hash_flag ?? false),
            'note'             => $order->note ?? null,

            'lines'            => $lines,
        ]);
    }

    /**
     * Modifica un ordine esistente.
     *
     * Regole:
     * - OCCASIONALE: flusso ATTUALE (verifica → prenota → PO sul delta), ammesso solo ai ruoli autorizzati (Policy).
     * - STANDARD confermato (status=1): Opzione A (conservativa) → dopo l'update NESSUN ricalcolo automatico
     *   (il CTA “Ricalcola approvvigionamenti” lancerà un job dedicato).
     * - STANDARD non confermato (status=0): comportamento identico allo STORE → nuovo token + invio mail
     *   (testo: “sostituisce e annulla il precedente”), e NESSUNA azione su riserve/PO ora.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        // Autorizzazione (Policy aggiornata negli step precedenti)
        //$this->authorize('update', $order);

        /*──────── VALIDAZIONE ────────*/
        $data = $request->validate([
            'delivery_date'      => ['required','date'],

            // header extra
            'hash_flag'          => ['sometimes','boolean'],
            'note'               => ['nullable','string'],

            // righe
            'lines'              => ['required','array','min:1'],
            'lines.*.product_id' => ['required','integer', Rule::exists('products','id')],
            'lines.*.quantity'   => ['required','numeric','min:0.01'],

            // prezzo opzionale (override finale NETTO)
            'lines.*.price'      => ['nullable','numeric','min:0'],

            // variabili
            'lines.*.fabric_id'  => ['nullable','integer', Rule::exists('fabrics','id')],
            'lines.*.color_id'   => ['nullable','integer', Rule::exists('colors','id')],

            // sconti multi-token (array di stringhe "N%" | "N")
            'lines.*.discount'   => ['nullable','array'],
            'lines.*.discount.*' => ['nullable','string'],
        ]);

        $customerId  = $order->customer_id; // guest → null
        $delivery    = Carbon::parse($data['delivery_date']);
        $canOverride = $request->user()->can('orders.price.override');

        /*──────── Header: aggiorno hash_flag e note (se presenti) ────────*/
        $order->fill([
            ...(array_key_exists('hash_flag', $data) ? ['hash_flag' => (bool)$data['hash_flag']] : []),
            ...(array_key_exists('note', $data)      ? ['note'      => $data['note']]           : []),
        ]);
        $order->save();

        /*──────── Helper sconti ────────*/
        $normalizeDiscountTokens = function ($raw): array {
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : [$raw];
            }
            if (!is_array($raw)) return [];
            $out = [];
            foreach ($raw as $tok) {
                if ($tok === null || $tok === '') continue;
                $s = Str::of((string)$tok)->trim();
                if ($s->endsWith('%')) {
                    $n = (float)str_replace('%','',(string)$s);
                    $out[] = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.') . '%';
                } else {
                    $n = (float)$s;
                    $out[] = rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
                }
            }
            return $out;
        };

        $applyDiscounts = function (float $unitGross, array $tokens): float {
            $net = $unitGross;
            foreach ($tokens as $t) {
                if (str_ends_with($t, '%')) {
                    $p = (float)str_replace('%','',$t);
                    $net -= $net * ($p / 100);
                } else {
                    $net -= (float)$t;
                }
            }
            return max(0.0, $net);
        };

        /*──────── Helper sovrapprezzi (coerenti con store) ────────*/
        $normType = function (?string $t): string {
            $t = strtolower((string)$t);
            return in_array($t, ['percent','percentage','%'], true) ? 'percent' : 'fixed';
        };
        $appliedAmount = function (float $base, string $type, ?float $value, float &$fixedSum, float &$percentSum): float {
            $v = (float)($value ?? 0);
            if ($type === 'percent') { $percentSum += $v; return $base * ($v / 100); }
            $fixedSum += $v; return $v;
        };
        $fabricMeta = function (Product $product, ?int $fabricId): array {
            if (!$fabricId) return ['type'=>null,'value'=>null];
            $pf = $product->fabrics()->where('fabrics.id', $fabricId)->first();
            $type  = $pf?->pivot?->surcharge_type;
            $value = $pf?->pivot?->surcharge_value;
            if ($type === null || $value === null) {
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
            if ($type === null || $value === null) {
                $col   = Color::select('surcharge_type','surcharge_value')->find($colorId);
                $type  = $col?->surcharge_type;
                $value = $col?->surcharge_value;
            }
            return ['type'=>$type, 'value'=>$value];
        };
        $resolveResolvedComponentId = function (Product $product, ?int $fabricId, ?int $colorId): ?int {
            $placeholder = $product->variableComponent();    // riga BOM “slot”
            if (! $placeholder) return null;
            $qid = Component::query()
                ->where('category_id', $placeholder->category_id)
                ->when($fabricId, fn($q)=>$q->where('fabric_id', $fabricId))
                ->when($colorId,  fn($q)=>$q->where('color_id',  $colorId))
                ->value('id');
            return $qid ?: $placeholder->id;
        };

        /*──────── PREPARAZIONE RIGHE (prezzo + sconti + variabili + meta) ────────*/
        $prepared = collect($data['lines'])->map(function(array $l) use (
            $customerId, $delivery, $canOverride,
            $normalizeDiscountTokens, $applyDiscounts,
            $normType, $appliedAmount, $fabricMeta, $colorMeta, $resolveResolvedComponentId
        ) {
            $productId = (int) $l['product_id'];
            $qty       = (float) $l['quantity'];
            $fabricId  = array_key_exists('fabric_id', $l) && $l['fabric_id'] !== null ? (int) $l['fabric_id'] : null;
            $colorId   = array_key_exists('color_id',  $l) && $l['color_id']  !== null ? (int) $l['color_id']  : null;
            $tokens    = $normalizeDiscountTokens($l['discount'] ?? []);

            /** @var \App\Models\Product $product */
            $product   = Product::findOrFail($productId);

            // whitelist sicurezza
            if ($fabricId !== null && ! in_array($fabricId, $product->fabricIds(), true)) {
                abort(response()->json(['message' => "Il tessuto selezionato non è consentito per il prodotto #{$productId}."], 422));
            }
            if ($colorId !== null && ! in_array($colorId, $product->colorIds(), true)) {
                abort(response()->json(['message' => "Il colore selezionato non è consentito per il prodotto #{$productId}."], 422));
            }

            // Breakdown prezzo da modello (lordo variabili)
            $fb = $product->pricingBreakdown($fabricId, $colorId, $customerId, $delivery->toDateString());
            if ($fb === null) {
                abort(response()->json(['message' => 'Prezzo non disponibile per uno dei prodotti nella data indicata.'], 422));
            }
            $unitGross = (float) $fb['unit_price'];

            // Override prezzo: se consentito e passato, consideralo NETTO finale; altrimenti applica sconti
            $unitNet = ($canOverride && array_key_exists('price', $l) && $l['price'] !== null && $l['price'] !== '')
                ? (float) $l['price']
                : $applyDiscounts($unitGross, $tokens);

            // Meta sovrapprezzi applicati (solo diagnostica)
            $base = (float) ($fb['base_price'] ?? 0.0);
            $fixedSum   = 0.0;
            $percentSum = 0.0;

            $fm = $fabricMeta($product, $fabricId);
            $cm = $colorMeta($product,  $colorId);

            $fabricAmt = 0.0; $colorAmt = 0.0;
            if ($fm['type'] !== null) {
                $fabricType = $normType($fm['type']);
                $fabricAmt  = $appliedAmount($base, $fabricType, (float)$fm['value'], $fixedSum, $percentSum);
            }
            if ($cm['type'] !== null) {
                $colorType  = $normType($cm['type']);
                $colorAmt   = $appliedAmount($base, $colorType, (float)$cm['value'], $fixedSum, $percentSum);
            }
            $surchargeTotalApplied = $fabricAmt + $colorAmt;

            // componente effettivo risolto (slot variabile BOM)
            $resolvedComponentId = $resolveResolvedComponentId($product, $fabricId, $colorId);

            return [
                // chiave logica per diff (prodotto+variabili)
                'key'        => sprintf('%d:%d:%d', $productId, $fabricId ?? 0, $colorId ?? 0),

                // dati riga
                'product_id' => $productId,
                'quantity'   => $qty,
                'price'      => (string) $unitNet,  // NETTO post sconti (congelato)
                'discount'   => $tokens,            // ← salva i token

                // variabili
                'fabric_id'  => $fabricId,
                'color_id'   => $colorId,
                'resolved_component_id'     => $resolvedComponentId,

                // meta sovrapprezzi (diagnostica)
                'surcharge_fixed_applied'   => $fixedSum,
                'surcharge_percent_applied' => $percentSum,
                'surcharge_total_applied'   => $surchargeTotalApplied,
            ];
        })->values();

        /*──────── BUSINESS LOGIC: upsert righe (+ eventuali materiali/PO gestiti dal Service) ────────*/
        $svc = app(OrderUpdateService::class);

        $result = $svc->handle(
            $order,
            $prepared,                        // include variabili + sconti + applied
            $delivery->toDateString()
        );

        // ── Branching post-update per STANDARD ─────────────────────────────────────
        if (is_null($order->occasional_customer_id)) {
            if ((int) $order->status === 0) {
                // STANDARD NON confermato → come store: rigenera token + invia mail (nessun materiale/PO adesso)
                $order->confirm_token             = (string) Str::uuid();
                $order->reason                    = null; // reset eventuale motivo rifiuto
                $order->confirmation_requested_at = now();
                $order->confirm_locale            = app()->getLocale();
                $order->save();

                // Invia mail di conferma con token (code + locale)
                Mail::to($order->customer->email)->queue(new OrderConfirmationRequestMail(
                    order: $order,
                    replacePrevious: $isUpdate ?? false // true nell'update standard non confermato
                ));

                return response()->json([
                    'order_id'               => $order->id,
                    'message'                => 'Richiesta conferma inviata al cliente (sostituisce e annulla la precedente).',
                    'awaiting_confirmation'  => true,
                    'result'                 => $result,
                ]);
            }

            // STANDARD confermato → Opzione A: nessun ricalcolo automatico
            return response()->json([
                'order_id'         => $order->id,
                'message'          => 'Ordine aggiornato.',
                'recalc_available' => true,   // il FE può mostrare il CTA “Ricalcola approvvigionamenti”
                'result'           => $result,
            ]);
        }

        // ── Occasionali: flusso attuale eseguito dal Service ───────────────────────
        return response()->json([
            'order_id' => $order->id,
            'message'  => 'Ordine occasionale aggiornato (flusso attuale eseguito).',
            'result'   => $result,
        ]);
    }

    /**
     * Ricalcola le prenotazioni materiali e crea nuovi PO se servono.
     *
     * Regole:
     * - SOLO per ordini STANDARD confermati (status=1).
     * - Se la consegna è entro 30 giorni dalla conferma, si possono creare nuovi PO.
     * - Se la consegna è oltre 30 giorni dalla conferma, NON si creano nuovi PO.
     * - Il ricalcolo integra le prenotazioni esistenti (su incoming libero e stock fisico),
     *   ma NON rilascia prenotazioni già fatte (sia su incoming che su stock).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function recalcProcurement(Request $request, Order $order): JsonResponse
    {
        // 1) Solo STANDARD confermati
        if (!is_null($order->occasional_customer_id)) {
            return response()->json(['message' => 'Azione non prevista per ordini occasionali.'], 422);
        }
        if ((int) $order->status !== 1) {
            return response()->json(['message' => 'Disponibile solo per ordini standard confermati.'], 422);
        }

        // 2) Calcolo regola < 30 giorni per la CREAZIONE di nuovi PO
        $confirmedAt = Carbon::parse($order->confirmed_at);
        $deliveryAt  = Carbon::parse($order->delivery_date);
        $daysDiff    = $confirmedAt->diffInDays($deliveryAt, false); // negativo se consegna < conferma
        $eligibleForPo = $daysDiff >= 0 && $daysDiff < 30;

        // 3) Snapshot righe → array {product_id, quantity, fabric_id, color_id}
        $order->load(['items.variable']);
        $usedLines = $order->items->map(function ($it) {
            return [
                'product_id' => $it->product_id,
                'quantity'   => (float) $it->quantity,
                'fabric_id'  => $it->variable?->fabric_id,
                'color_id'   => $it->variable?->color_id,
            ];
        })->values()->all();

        // 4) TX: integrazioni su incoming libero + prenotazioni STOCK fisico
        DB::transaction(function () use ($order, $usedLines, $deliveryAt) {

            // 4.a) Fabbisogno per componente
            $componentsNeeded = InventoryServiceExtensions::explodeBomArray($usedLines);

            // 4.b) Prenota quantità libere su PO esistenti (nessuna riduzione: solo integrazioni)
            InventoryServiceExtensions::reserveFreeIncoming(
                $order,
                $componentsNeeded,
                $deliveryAt
            );

            // 4.c) Verifica copertura aggiornata (tenendo conto anche delle mie incoming reservation)
            $invResult = InventoryService::forDelivery($order->delivery_date, $order->id)->check($usedLines);

            // 4.d) Prenota da STOCK fisico quanto possibile (solo integrazioni; nessun rilascio)
            foreach ($invResult->shortage as $row) {
                $missing   = (float) $row['shortage'];   // fabbisogno ancora scoperto
                $fromStock = (float) min($row['available'], $missing);

                if ($fromStock > 0) {
                    // FIFO sui lotti del componente, con lock per concorrenza
                    $levels = StockLevel::query()
                        ->where('component_id', (int) $row['component_id'])
                        ->orderBy('created_at')
                        ->lockForUpdate()
                        ->get();

                    $needLeft = $fromStock;

                    foreach ($levels as $sl) {
                        if ($needLeft <= 0) break;

                        $already = (float) StockReservation::query()
                            ->where('stock_level_id', $sl->id)
                            ->sum('quantity');

                        $free = (float) $sl->quantity - $already;
                        if ($free <= 0) continue;

                        $take = min($free, $needLeft);
                        if ($take <= 0) continue;

                        StockReservation::create([
                            'stock_level_id' => $sl->id,
                            'order_id'       => $order->id,
                            'quantity'       => $take,
                        ]);

                        StockMovement::create([
                            'stock_level_id' => $sl->id,
                            'type'           => 'reserve',
                            'quantity'       => $take,
                            'note'           => "Prenotazione stock per OC #{$order->id} (CTA ricalcolo)",
                        ]);

                        $needLeft -= $take;
                    }
                }
            }
        });

        // 5) Verifica finale e creazione PO (SOLO se < 30 giorni)
        $poNumbers = [];
        $invFinal  = InventoryService::forDelivery($order->delivery_date, $order->id)->check($usedLines);

        if (!$invFinal->ok && $eligibleForPo) {
            $shortCol  = ProcurementService::buildShortageCollection($invFinal->shortage);
            $proc      = ProcurementService::fromShortage($shortCol, $order->id);
            $poNumbers = $proc['po_numbers']->all();
        }

        return response()->json([
            'recalc_performed' => true,
            'eligible_for_po'  => $eligibleForPo,
            'po_numbers'       => $poNumbers,
            'message'          => $eligibleForPo
                ? (count($poNumbers) ? 'Ricalcolo completato: creati nuovi PO.' : 'Ricalcolo completato: nessun nuovo PO necessario.')
                : 'Ricalcolo completato: consegna non entro 30 giorni dalla conferma, nessun nuovo PO creato.',
        ]);
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
