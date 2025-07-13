<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
