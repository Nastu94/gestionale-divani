<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

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
            ->with('supplier:id,name')             // eager-load nome fornitore
            ->where('cause', 'purchase')           // solo ordini fornitore
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
