<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNumber;
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
        /*──────────────── PARAMETRI DI QUERY ────────────────*/
        $sort    = $request->input('sort', 'ordered_at');           // campo di ordinamento di default
        $dir     = $request->input('dir',  'desc') === 'asc' ? 'asc' : 'desc';
        $filters = $request->input('filter', []);                   // eventuali filtri colonna

        /*──────────────── WHITELIST ORDINABILE ───────────────*/
        $allowedSorts = ['id', 'customer', 'ordered_at', 'delivery_date', 'total'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'ordered_at';
        }

        /*──────────────── QUERY PRINCIPALE ───────────────────*/
        $orders = Order::query()
            ->with(['customer:id,company', 'orderNumber:id,number,order_type'])  // eager-load relazioni
            ->whereHas('orderNumber', fn ($q) =>
                $q->where('order_type', 'customer')                              // solo ordini *cliente*
            )
            /*────────────── FILTRI SINGOLE COLONNE ───────────*/
            ->when($filters['id']           ?? null,
                   fn ($q,$v) => $q->where('id', $v))
            ->when($filters['customer']     ?? null,
                   fn ($q,$v) => $q->whereHas('customer',
                                   fn ($q) => $q->where('company','like',"%$v%")))
            ->when($filters['ordered_at']   ?? null,
                   fn ($q,$v) => $q->whereDate('ordered_at', $v))
            ->when($filters['delivery_date']?? null,
                   fn ($q,$v) => $q->whereDate('delivery_date', $v))
            ->when($filters['total']        ?? null,
                   fn ($q,$v) => $q->where('total','like',"%$v%"))
            /*────────────── ORDINAMENTO DINAMICO ─────────────*/
            ->when($sort === 'customer', function ($q) use ($dir) {
                /* join per ordinare sulla ragione sociale */
                $q->join('customers as c','orders.customer_id','=','c.id')
                  ->orderBy('c.company', $dir)
                  ->select('orders.*');
            }, function ($q) use ($sort, $dir) {
                $q->orderBy($sort, $dir);
            })
            ->paginate(15)
            ->appends($request->query());                                    // preserva query-string

        /*──────────────── VIEW ───────────────────────────────*/
        return view(
            'pages.orders.index-customers',
            compact('orders','sort','dir','filters')
        );
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
            'user_id' => $request->user() ? $request->user()->id : null,
            'payload' => $request->all(),
        ]);

        /*─────────── VALIDAZIONE ───────────*/
        $data = $request->validate([
            'order_number_id'        => ['required', 'integer', Rule::exists('order_numbers', 'id')],
            'customer_id'            => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'occasional_customer_id' => ['nullable', 'integer', Rule::exists('occasional_customers', 'id')],
            'delivery_date'          => ['required', 'date'],
            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.product_id'     => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.quantity'       => ['required', 'numeric', 'min:0.01'],
            'lines.*.price'          => ['required', 'numeric', 'min:0'],
        ]);

        /* esclusività customer_id / occasional_customer_id */
        if (! ($data['customer_id'] xor $data['occasional_customer_id'] ?? null)) {
            Log::warning('OrderCustomer@store – violazione esclusività customer vs occasional', $data);
            return response()->json([
                'message' => 'Indicare solo customer_id oppure occasional_customer_id.'
            ], 422);
        }

        try {
            /*────── TRANSAZIONE ATOMICA ──────*/
            $order = DB::transaction(function () use ($data) {

                /* 1. verifica & blocca OrderNumber */
                $orderNumber = OrderNumber::where('id', $data['order_number_id'])
                    ->where('order_type', 'customer')
                    ->with('order')
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($orderNumber->order) {
                    Log::warning('OrderCustomer@store – OrderNumber già usato', [
                        'order_number_id' => $orderNumber->id,
                    ]);
                    abort(409, 'OrderNumber già assegnato.');
                }

                /* 2. calcolo totale */
                $total = collect($data['lines'])->reduce(
                    fn (float $s, $l) => $s + ($l['quantity'] * $l['price']),
                    0.0
                );

                /* 3. inserimento ordine */
                $order = Order::create([
                    'order_number_id'        => $orderNumber->id,
                    'customer_id'            => $data['customer_id']            ?? null,
                    'occasional_customer_id' => $data['occasional_customer_id'] ?? null,
                    'total'                  => $total,
                    'ordered_at'             => now(),
                    'delivery_date'          => $data['delivery_date'],
                ]);

                /* 4. inserimento righe */
                foreach ($data['lines'] as $line) {
                    $order->items()->create([
                        'product_id' => $line['product_id'],
                        'quantity'   => $line['quantity'],
                        'unit_price' => $line['price'],
                    ]);
                }

                Log::info('OrderCustomer@store – ordine e righe salvati', [
                    'order_id' => $order->id,
                    'lines'    => count($data['lines']),
                ]);

                return $order->load(['items.product', 'orderNumber']);
            });

            return response()->json($order, 201);

        } catch (\Throwable $e) {
            Log::error('OrderCustomer@store – eccezione', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 1024),
            ]);

            return response()->json([
                'message' => 'Errore interno durante il salvataggio dell’ordine.',
            ], 500);
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
