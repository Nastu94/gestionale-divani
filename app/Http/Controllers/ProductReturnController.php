<?php
/**
 * Controller: ProductReturnController@index
 *
 * Elenco resi con:
 *  - Autorizzazione: 'orders.customer.returns_manage'
 *  - Filtri per-colonna stile <x-th-menu>:
 *      • filter[number]   → LIKE su product_returns.number
 *      • filter[customer] → LIKE su customers.company (join on demand)
 *  - Sorting sicuro (whitelist):
 *      • number        → product_returns.number
 *      • return_date   → product_returns.return_date
 *      • customer      → customers.company (join on demand)
 *  - Stato (solo amministrativo / in magazzino) via withCount delle righe restock=true
 *  - Paginazione con query string preservata
 *  - Iniezione dataset per il modale: customers, products, fabrics, colors, warehouses, returnWarehouseId
 *
 * PHP 8.4 / Laravel 12 — Commenti secondo convenzioni del progetto.
 */

namespace App\Http\Controllers;

use App\Models\ProductReturn;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Fabric;
use App\Models\Color;
use App\Models\Warehouse;
use App\Models\ProductReturnLine;
use App\Models\ProductStockLevel;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductReturnController extends Controller
{
    /**
     * Elenco resi con filtri/ordinamento e dataset per il modale.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // ───────────────────────────────── Autorizzazione ─────────────────────────────────
        if (Gate::denies('orders.customer.returns_manage')) {
            abort(403);
        }

        // ───────────────────────────────── Parametri UI (filtri + sort) ─────────────────────────────────
        $filters = (array) $request->query('filter', []);
        $filters = [
            'number'   => isset($filters['number'])   ? trim((string) $filters['number'])   : '',
            'customer' => isset($filters['customer']) ? trim((string) $filters['customer']) : '',
        ];

        $sort = (string) $request->query('sort', 'return_date');
        $dir  = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Campi ordinabili → colonne SQL
        $sortable = [
            'number'      => 'product_returns.number',
            'return_date' => 'product_returns.return_date',
            'customer'    => 'customers.company',
        ];

        if (! array_key_exists($sort, $sortable)) {
            $sort = 'return_date';
        }

        // ───────────────────────────────── Query base ─────────────────────────────────
        $q = ProductReturn::query()
            ->select('product_returns.*')
            ->with(['customer']) // per la stampa del cliente in tabella
            ->withCount(['lines as restock_lines_count' => function ($qq) {
                $qq->where('restock', true);
            }]);

        $needsCustomerJoin = ($filters['customer'] !== '') || ($sort === 'customer');

        if ($needsCustomerJoin) {
            $q->leftJoin('customers', 'customers.id', '=', 'product_returns.customer_id');
        }

        // ───────────────────────────────── Filtri per-colonna ─────────────────────────────────
        if ($filters['number'] !== '') {
            // LIKE su number con escape minimo del simbolo %
            $q->where('product_returns.number', 'like', '%' . str_replace('%', '\%', $filters['number']) . '%');
        }

        if ($filters['customer'] !== '') {
            // LIKE su customers.company (join già presente se necessario)
            $q->where('customers.company', 'like', '%' . str_replace('%', '\%', $filters['customer']) . '%');
        }

        // ───────────────────────────────── Ordinamento & Paginazione ─────────────────────────────────
        $q->orderBy($sortable[$sort], $dir)
          ->orderBy('product_returns.id', 'desc'); // stabilizza a parità di campo

        $perPage = (int) $request->query('per_page', 25);
        if ($perPage < 1)   $perPage = 25;
        if ($perPage > 100) $perPage = 100;

        $returns = $q->paginate($perPage)->appends($request->query());

        // ───────────────────────────────── Dataset per il modale ─────────────────────────────────
        // NB: niente logica extra, solo liste per select/autocomplete del form
        $customers = Customer::query()
            ->orderBy('company')
            ->get(['id', 'company']); // prendiamo entrambi i campi per compatibilità UI

        $products = Product::query()
            ->orderBy('sku')
            ->get(['id', 'sku', 'name']);

        $fabrics = Fabric::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $colors = Color::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        $returnWarehouseId = Warehouse::where('type', 'return')->value('id'); // id di MG-RETURN

        // ───────────────────────────────── Render view ─────────────────────────────────
        return view('pages.orders.returns.index', [
            // tabella
            'returns' => $returns,
            'filters' => $filters,
            'sort'    => $sort,
            'dir'     => $dir,

            // modale
            'customers'         => $customers,
            'products'          => $products,
            'fabrics'           => $fabrics,
            'colors'            => $colors,
            'warehouses'        => $warehouses,
            'returnWarehouseId' => $returnWarehouseId,
        ]);
    }

    /**
     * Dettaglio reso per modale (edit) – JSON.
     *
     * Ritorna:
     *  - testata (number, return_date ISO, notes)
     *  - customer { id, company|name, email, shipping_address }
     *  - order    { id, number, ordered_at, delivery_date, shipping_address, customer{...} }
     *  - lines    [...]
     */
    public function show(Request $request, ProductReturn $return)
    {
        Gate::authorize('orders.customer.returns_manage');

        // Eager load completi per evitare N+1:
        $return->load([
            // cliente con email + indirizzo di spedizione principale
            'customer:id,company,email',
            'customer.shippingAddress:id,customer_id,address,postal_code,city,country',

            // ordine con numero + cliente (per riepilogo) 
            'order.orderNumber',                           // ->number
            'order.customer:id,company,email',

            // righe + riferimenti (prodotto / variabili)
            'lines' => fn ($q) => $q->orderBy('id'),
            'lines.product:id,sku,name',
            'lines.fabric:id,name',
            'lines.color:id,name',
        ]);

        if (! $request->wantsJson()) {
            return redirect()->route('returns.index');
        }

        // Helper: formatta un indirizzo CustomerAddress → stringa leggibile
        $fmtAddr = function ($addr) {
            if (! $addr) return null;
            $parts = array_filter([
                $addr->address,
                trim(($addr->postal_code ? $addr->postal_code.' ' : '').($addr->city ?? '')) ?: null,
                $addr->province ?: null,
                $addr->country  ?: null,
            ]);
            return $parts ? implode(', ', $parts) : null;
        };

        // Se l'ordine esiste, la priorità è il suo shipping_address;
        // altrimenti usiamo lo shippingAddress del cliente.
        $orderShipping = $return->order?->shipping_address;
        $customerShipping = $fmtAddr($return->customer?->shippingAddress);
        $shippingForHeader = $orderShipping ?: $customerShipping;

        $out = [
            'id'          => $return->id,
            'number'      => $return->number,
            // input[type=date] → ISO YYYY-MM-DD
            'return_date' => optional($return->return_date)->format('Y-m-d'),
            'notes'       => $return->notes,

            // Cliente testata (read-only in edit)
            'customer'    => $return->customer ? [
                'id'               => $return->customer->id,
                'company'          => $return->customer->company,
                'email'            => $return->customer->email,
                'shipping_address' => $shippingForHeader,   // ← già risolto sopra
            ] : null,

            // Ordine associato (read-only in edit)
            'order'       => $return->order ? [
                'id'              => $return->order->id,
                'number'          => optional($return->order->orderNumber)->number, // garantisce il numero
                'ordered_at'      => optional($return->order->ordered_at)->format('d/m/Y'),
                'delivery_date'   => optional($return->order->delivery_date)->format('d/m/Y'),
                'shipping_address'=> $orderShipping,
                'customer'        => $return->order->customer ? [
                    'id'      => $return->order->customer->id,
                    'company' => $return->order->customer->company,
                    'email'   => $return->order->customer->email,
                ] : null,
            ] : null,

            // Righe
            'lines'       => $return->lines->map(function ($l) {
                return [
                    'id'          => $l->id,
                    'product_id'  => $l->product_id,
                    'product'     => $l->product ? [
                        'id'   => $l->product->id,
                        'sku'  => $l->product->sku,
                        'name' => $l->product->name,
                    ] : null,
                    'quantity'    => (float) $l->quantity,
                    'fabric_id'   => $l->fabric_id,
                    'color_id'    => $l->color_id,
                    'fabric_name' => $l->fabric->name ?? null,
                    'color_name'  => $l->color->name ?? null,
                    'condition'   => $l->condition,
                    'reason'      => $l->reason,
                    'note'        => $l->note,
                    'restock'     => (bool) $l->restock,
                ];
            })->values(),
        ];

        return response()->json($out);
    }

    /**
     * Salva un nuovo reso + eventuale rientro a magazzino “MG-RETURN”.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // ───────────── Autorizzazione ─────────────
        Gate::authorize('orders.customer.returns_manage');

        Log::info('@ProductReturnController::store, Dati ricevuti:', ['request' => $request->all()]);

        /* =========================================================
        * NORMALIZZAZIONE PAYLOAD (prima della validate)
        * - Se arriva lines_json (stringa), decodifichiamo in array
        * - Mappiamo product → product_id
        * - Rinominiamo notes → note
        * - Cast quantity / restock
        * ========================================================= */
        $rawLines = $request->input('lines');

        if (empty($rawLines) && $request->filled('lines_json')) {
            $decoded = json_decode($request->input('lines_json'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $rawLines = $decoded;
            }
        }

        $normalized = [];
        foreach ((array) $rawLines as $idx => $l) {
            // prodotto: accetta sia product_id che product.id
            $productId = $l['product_id'] ?? ($l['product']['id'] ?? null);

            $normalized[] = [
                'product_id' => $productId,
                'quantity'   => isset($l['quantity']) ? (float) $l['quantity'] : (isset($l['qty']) ? (float) $l['qty'] : 1),
                'fabric_id'  => $l['fabric_id'] ?? null,
                'color_id'   => $l['color_id'] ?? null,
                'condition'  => $l['condition'] ?? null,
                'reason'     => $l['reason'] ?? null,
                'note'       => $l['note'] ?? ($l['notes'] ?? null),  // rinomina notes → note
                'restock'    => (bool) ($l['restock'] ?? false),
            ];
        }

        // sostituiamo/aggiungiamo "lines" al request così la validate funziona
        $request->merge([
            'lines'     => $normalized,
            // normalizza order_id vuoto → null
            'order_id'  => $request->filled('order_id') ? $request->input('order_id') : null,
            // normalizza notes testata stringa vuota → null
            'notes'     => ($request->filled('notes') && trim((string)$request->input('notes')) !== '') ? $request->input('notes') : null,
        ]);

        // ───────────── Validazione input ─────────────
        $data = $request->validate([
            'number'       => 'required|string|max:50',
            'return_date'  => 'required|date',
            'customer_id'  => 'required|integer|exists:customers,id',
            'order_id'     => 'nullable|integer|exists:orders,id',
            'notes'        => 'nullable|string',

            'lines'                        => 'required|array|min:1',
            'lines.*.product_id'          => 'required|integer|exists:products,id',
            'lines.*.quantity'            => 'required|numeric|min:0.01',
            'lines.*.fabric_id'           => 'nullable|integer|exists:fabrics,id',
            'lines.*.color_id'            => 'nullable|integer|exists:colors,id',
            'lines.*.condition'           => 'nullable|string|max:255',
            'lines.*.reason'              => 'nullable|string|max:255',
            'lines.*.note'                => 'nullable|string',
            'lines.*.restock'             => 'nullable|boolean',
        ]);

        Log::info('@ProductReturnController::store, Dati validati:', ['data' => $data]);

        // Recupera (o fallisce) il magazzino resi: type = 'return' (fallback su code='MG-RETURN')
        $returnsWarehouse = Warehouse::query()
            ->where('type', 'return')
            ->orWhere('code', 'MG-RETURN')
            ->first();

        if (! $returnsWarehouse) {
            Log::error('@ProductReturnController::store, Magazzino resi non trovato.');
            // Evitiamo salvataggi “a metà” se manca il magazzino resi
            return $this->respondError(
                'Magazzino resi non configurato (type=return / code=MG-RETURN).'
            );
        }

        // Se viene passato un ordine a cui abbinare il reso, verifichiamo che esista
        $order = null;
        if (!empty($data['order_id'])) {
            $order = Order::find($data['order_id']); // già validato sopra
        }

        Log::info('@ProductReturnController::store, Magazzino e Ordine corrispondenti:', ['returnsWarehouse' => $returnsWarehouse, 'order' => $order]);

        // ───────────── Persistenza atomica ─────────────
        try {
            $result = DB::transaction(function () use ($data, $returnsWarehouse, $order) {

                // 1) Testata reso
                /** @var ProductReturn $ret */
                $ret = ProductReturn::create([
                    'number'       => $data['number'],
                    'return_date'  => $data['return_date'],
                    'customer_id'  => $data['customer_id'],
                    'order_id'     => $data['order_id'] ?? null,
                    'notes'        => $data['notes']     ?? null,
                ]);

                Log::info('@ProductReturnController::store, Dati reso creati:', ['return' => $ret]);

                // 2) Righe + eventuale entrata a magazzino resi
                foreach ($data['lines'] as $row) {
                    /** @var ProductReturnLine $line */
                    $line = $ret->lines()->create([
                        'product_id' => $row['product_id'],
                        'quantity'   => $row['quantity'],
                        'fabric_id'  => $row['fabric_id'] ?? null,
                        'color_id'   => $row['color_id']  ?? null,
                        'condition'  => $row['condition'] ?? null,
                        'reason'     => $row['reason']    ?? null,
                        'note'       => $row['note']      ?? null,
                        'restock'    => (bool)($row['restock'] ?? false),
                        'warehouse_id'=> $returnsWarehouse->id,
                    ]);

                    // 2.a) Se “in magazzino”, creiamo la giacenza di prodotto finito
                    if ($line->restock) {
                        // Creiamo una giacenza di prodotto finito in “MG-RETURN”
                        $psl = ProductStockLevel::create([
                            'product_id'   => $line->product_id,
                            'fabric_id'    => $line->fabric_id,
                            'color_id'     => $line->color_id,
                            'warehouse_id' => $returnsWarehouse->id,
                            'quantity'     => $line->quantity,
                            'reserved_for' => null,
                            // ordine di ORIGINE del reso (come da tua specifica)
                            'order_id'     => (int) $data['order_id'],
                        ]);

                        // Collega in modo sicuro la riga del reso alla PSL creata
                        $line->product_stock_level_id = $psl->id;
                        $line->save();
                    }
                }

                Log::info('@ProductReturnController::store, Dati reso creati:', ['return' => $ret]);

                return $ret;
            });
        } catch (\Throwable $e) {
            report($e);
            return $this->respondError('Errore durante il salvataggio del reso.');
        }

        // ───────────── Risposta coerente: JSON per fetch, redirect per form ─────────────
        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'id'      => $result->id,
                'number'  => $result->number,
                'message' => 'Reso salvato correttamente.',
            ]);
        }

        return redirect()
            ->route('returns.index')
            ->with('success', 'Reso salvato correttamente.');
    }

    /**
     * Helper risposta errore uniforme
     * @param  string  $msg
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    private function respondError(string $msg)
    {
        if (request()->wantsJson()) {
            return response()->json(['ok' => false, 'message' => $msg], 422);
        }
        return redirect()->back()->with('error', $msg);
    }

    /**
     * Aggiorna un reso + eventuale rientro a magazzino “MG-RETURN”.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProductReturn  $return
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ProductReturn $return)
    {
        Gate::authorize('orders.customer.returns_manage');

        Log::info('@ProductReturnController::update [IN]', [
            'return_id' => $return->id,
            'payload'   => $request->all(),
        ]);

        // Normalizza le righe dal payload (supporta lines_json o lines[])
        $lines = $this->normalizeLinesFromRequest($request);

        // Validazione base testata
        $request->validate([
            'number'      => 'required|string|max:50',
            'return_date' => 'required|date',
            'customer_id' => 'required|integer|exists:customers,id',
            'order_id'    => 'nullable|integer|exists:orders,id',
            'notes'       => 'nullable|string',
        ]);

        // Se c’è almeno un restock, serve l’ordine di origine
        $hasRestock = collect($lines)->contains(fn ($r) => !empty($r['restock']));
        if ($hasRestock && empty($request->order_id)) {
            return $this->respondError('Per rimettere a stock (restock) serve un ordine di origine.');
        }

        // Magazzino resi
        $returnsWarehouse = Warehouse::query()
            ->where('type', 'return')
            ->orWhere('code', 'MG-RETURN')
            ->first();

        if (!$returnsWarehouse) {
            return $this->respondError('Magazzino resi non configurato (type=return / code=MG-RETURN).');
        }

        try {
            DB::transaction(function () use ($request, $return, $lines, $returnsWarehouse) {

                // Aggiorna testata
                $return->number      = $request->input('number', $return->number);
                $return->return_date = $request->input('return_date', $return->return_date);
                $return->customer_id = $request->input('customer_id', $return->customer_id);
                $return->order_id    = $request->input('order_id', $return->order_id); // ordine di ORIGINE del reso
                $return->notes       = $request->input('notes');
                $return->save();

                $originOrderId = (int) $return->order_id;

                // Upsert righe + sincronizzazione stock in MG-RETURN
                $this->upsertReturnLines($return, $returnsWarehouse, $lines, $originOrderId);
            });
        } catch (\Throwable $e) {
            Log::error('@ProductReturnController::update [EXCEPTION]', [
                'return_id' => $return->id,
                'err'       => $e->getMessage(),
            ]);
            report($e);
            return $this->respondError('Errore durante l\'aggiornamento del reso.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'id'      => $return->id,
                'number'  => $return->number,
                'message' => 'Reso aggiornato correttamente.',
            ]);
        }

        return redirect()
            ->route('returns.show', $return)
            ->with('success', 'Reso aggiornato correttamente.');
    }

    /* ==========================================================
    |  Metodi di supporto
    * ========================================================*/

    /**
     * Converte lines_json (o lines[]) nel formato canonico:
     * [
     *   ['product_id'=>..,'quantity'=>..,'fabric_id'=>..,'color_id'=>..,
     *    'condition'=>..,'reason'=>..,'note'=>..,'restock'=>bool ],
     *   ...
     * ]
     */
    private function normalizeLinesFromRequest(Request $request): array
    {
        $raw = $request->input('lines');
        if (is_null($raw)) {
            $raw = $request->input('lines_json');
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
            }
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            $prodId = data_get($row, 'product.id') ?? data_get($row, 'product_id');
            if (!$prodId) {
                continue;
            }
            $out[] = [
                'product_id' => (int) $prodId,
                'quantity'   => (float) data_get($row, 'quantity', 0),
                'fabric_id'  => data_get($row, 'fabric_id') !== null ? (int) data_get($row, 'fabric_id') : null,
                'color_id'   => data_get($row, 'color_id')  !== null ? (int) data_get($row, 'color_id')  : null,
                'condition'  => data_get($row, 'condition') ?: null,
                'reason'     => data_get($row, 'reason')    ?: null,
                // la UI manda "notes": lo mappiamo su "note"
                'notes'       => (string) (data_get($row, 'note') ?? data_get($row, 'notes') ?? ''),
                'restock'    => (bool) data_get($row, 'restock', false),
            ];
        }
        return $out;
    }

    /** Chiave logica per confrontare righe (assumiamo 1 riga per prodotto×tessuto×colore). */
    private function lineKey(int $productId, ?int $fabricId, ?int $colorId): string
    {
        return $productId.'|'.($fabricId ?? 'null').'|'.($colorId ?? 'null');
    }

    /**
     * Upsert righe reso e sincronizza lo stock in magazzino resi.
     */
    private function upsertReturnLines(ProductReturn $return, Warehouse $w, array $incomingLines, int $originOrderId): void
    {
        $existing = $return->lines()
            ->get()
            ->keyBy(fn ($l) => $this->lineKey($l->product_id, $l->fabric_id, $l->color_id));

        $incomingByKey = [];
        foreach ($incomingLines as $r) {
            $k = $this->lineKey((int) $r['product_id'], $r['fabric_id'] ?? null, $r['color_id'] ?? null);
            $incomingByKey[$k] = $r;
        }

        // 1) Eliminazioni: righe presenti prima ma non più in input
        foreach ($existing as $key => $line) {
            if (!array_key_exists($key, $incomingByKey)) {
                $this->deleteLineAndStock($line, $w, $originOrderId);
            }
        }

        // 2) Creazioni/Aggiornamenti + sync stock
        foreach ($incomingByKey as $key => $row) {
            if ($existing->has($key)) {
                // Update
                /** @var ProductReturnLine $line */
                $line = $existing->get($key);
                $line->quantity  = $row['quantity'];
                $line->condition = $row['condition'];
                $line->reason    = $row['reason'];
                $line->notes     = $row['notes'];
                $line->restock   = $row['restock'];
                $line->save();
            } else {
                // Create
                /** @var ProductReturnLine $line */
                $line = $return->lines()->create([
                    'product_id' => $row['product_id'],
                    'quantity'   => $row['quantity'],
                    'fabric_id'  => $row['fabric_id'] ?? null,
                    'color_id'   => $row['color_id'] ?? null,
                    'condition'  => $row['condition'] ?? null,
                    'reason'     => $row['reason'] ?? null,
                    'notes'      => $row['notes'] ?? null,
                    'restock'    => (bool) ($row['restock'] ?? false),
                ]);
            }

            // Sincronizza la giacenza collegata alla riga (create/update/delete)
            $this->syncReturnLineStock($line, $w, $originOrderId);
        }
    }

    /** Elimina una riga e la sua eventuale PSL collegata (in MG-RETURN). */
    private function deleteLineAndStock(ProductReturnLine $line, Warehouse $w, int $originOrderId): void
    {
        $this->removePSLForLine($line, $w, $originOrderId);
        $line->delete();
    }

    /** Rimuove la PSL associata a una riga (via FK o via chiavi logiche). */
    private function removePSLForLine(ProductReturnLine $line, Warehouse $w, int $originOrderId): void
    {
        if ($line->product_stock_level_id) {
            ProductStockLevel::where('id', $line->product_stock_level_id)->delete();
            $line->product_stock_level_id = null;
            $line->save();
            return;
        }

        // Fallback su chiavi logiche
        $q = ProductStockLevel::query()
            ->where('warehouse_id', $w->id)
            ->where('product_id', $line->product_id)
            ->where('order_id', $originOrderId)
            ->when($line->fabric_id !== null, fn ($qq) => $qq->where('fabric_id', $line->fabric_id),
                                        fn ($qq) => $qq->whereNull('fabric_id'))
            ->when($line->color_id !== null,  fn ($qq) => $qq->where('color_id',  $line->color_id),
                                        fn ($qq) => $qq->whereNull('color_id'));

        if ($psl = $q->first()) {
            $psl->delete();
        }

        $line->product_stock_level_id = null;
        $line->save();
    }

    /**
     * Crea/aggiorna la PSL per una riga in MG-RETURN.
     * - Se restock=false → elimina la PSL (se esiste).
     * - Se restock=true  → crea/aggiorna PSL con order_id = ordine di ORIGINE del reso.
     *   La colonna reserved_for NON viene toccata (prenotazione la gestirai in seguito).
     */
    private function syncReturnLineStock(ProductReturnLine $line, Warehouse $w, int $originOrderId): void
    {
        if (!$line->restock) {
            $this->removePSLForLine($line, $w, $originOrderId);
            return;
        }

        // 1) Prova via FK già salvata sulla riga
        $psl = null;
        if ($line->product_stock_level_id) {
            $psl = ProductStockLevel::find($line->product_stock_level_id);
        }

        // 2) Fallback: cerca PSL per chiavi logiche
        if (!$psl) {
            $psl = ProductStockLevel::query()
                ->where('warehouse_id', $w->id)
                ->where('product_id', $line->product_id)
                ->where('order_id', $originOrderId)
                ->when($line->fabric_id !== null, fn ($q) => $q->where('fabric_id', $line->fabric_id),
                                            fn ($q) => $q->whereNull('fabric_id'))
                ->when($line->color_id !== null,  fn ($q) => $q->where('color_id',  $line->color_id),
                                            fn ($q) => $q->whereNull('color_id'))
                ->first();
        }

        $isNew = false;
        if (!$psl) {
            $psl = new ProductStockLevel();
            $isNew = true;
        }

        $psl->product_id   = $line->product_id;
        $psl->fabric_id    = $line->fabric_id;
        $psl->color_id     = $line->color_id;
        $psl->warehouse_id = $w->id;
        $psl->quantity     = $line->quantity;

        // 🔴 ordine di ORIGINE del reso
        $psl->order_id     = $originOrderId;

        // Non tocchiamo eventuali prenotazioni esistenti
        if ($isNew) {
            $psl->reserved_for = null;
        }

        $psl->save();

        // Allinea la FK sulla riga se non presente/variata
        if ($line->product_stock_level_id !== $psl->id) {
            $line->product_stock_level_id = $psl->id;
            $line->save();
        }
    }

    /**
     * Elimina un reso (solo se non ha righe “in magazzino”).
     */
    public function destroy(Request $request, ProductReturn $return)
    {
        Gate::authorize('orders.customer.returns_manage');

        Log::info('@ProductReturnController::destroy [IN]', ['return_id' => $return->id]);

        // Recupero magazzino resi per verifiche di coerenza
        $returnsWarehouse = Warehouse::query()
            ->where('type', 'return')
            ->orWhere('code', 'MG-RETURN')
            ->first();

        if (! $returnsWarehouse) {
            return $this->respondError('Magazzino resi non configurato (type=return / code=MG-RETURN).');
        }

        try {
            DB::transaction(function () use ($return, $returnsWarehouse) {

                // Carico righe (con eventuale id PSL)
                $lines = $return->lines()->get(['id','product_stock_level_id']);

                // Colleziono gli id PSL per lock/validazioni e cancellazione
                $pslIds = $lines->pluck('product_stock_level_id')->filter()->values();

                if ($pslIds->isNotEmpty()) {

                    // Lock ottimistico delle PSL coinvolte
                    $psls = ProductStockLevel::whereIn('id', $pslIds)->lockForUpdate()->get();

                    // Blocco se qualche PSL è già riservata o non è più nel magazzino resi
                    $blocked = $psls->first(function ($psl) use ($returnsWarehouse) {
                        return !is_null($psl->reserved_for) || (int)$psl->warehouse_id !== (int)$returnsWarehouse->id;
                    });

                    if ($blocked) {
                        $why = !is_null($blocked->reserved_for)
                            ? "già riservata all'ordine #{$blocked->reserved_for}"
                            : 'spostata fuori dal magazzino resi';
                        throw new \RuntimeException("esiste una giacenza da reso {$why} (PSL #{$blocked->id}).");
                    }
                }

                // 1) Cancello le righe del reso (prima, per non violare il FK sulla PSL)
                if ($lines->isNotEmpty()) {
                    ProductReturnLine::whereIn('id', $lines->pluck('id'))->delete();
                }

                // 2) Cancello le PSL collegate (ora non più referenziate)
                if ($pslIds->isNotEmpty()) {
                    ProductStockLevel::whereIn('id', $pslIds)->delete();
                }

                // 3) Cancello la testata del reso
                $return->delete();

                Log::info('@ProductReturnController::destroy [OK]', [
                    'return_id' => $return->id,
                    'deleted_lines' => $lines->count(),
                    'deleted_psl'   => $pslIds->count(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('@ProductReturnController::destroy [EXCEPTION]', [
                'return_id' => $return->id,
                'err'       => $e->getMessage(),
            ]);
            return $this->respondError('Impossibile eliminare il reso: ' . $e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Reso eliminato correttamente.',
            ]);
        }

        return redirect()
            ->route('returns.index')
            ->with('success', 'Reso eliminato correttamente.');
    }
}
