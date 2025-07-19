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

                // 5.2 Per ogni componente, prenota in stock_reservations  
                foreach ($invResult->shortage as $row) {
                    $needed    = $row['needed'];
                    $have      = $row['available'] + $row['incoming'] + $row['my_incoming'];
                    $fromStock = min($row['available'], $needed);  // quanto effettivamente prelevato

                    if ($fromStock > 0) {
                        // prendo uno StockLevel qualsiasi
                        $sl = StockLevel::where('component_id', $row['component_id'])
                                ->orderBy('quantity')
                                ->first();

                        // 5.2.1 crea prenotazione
                        StockReservation::create([
                            'stock_level_id' => $sl->id,
                            'order_id'       => $order->id,
                            'quantity'       => $fromStock,
                        ]);

                        // 5.2.2 registra movimento magazzino
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
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
