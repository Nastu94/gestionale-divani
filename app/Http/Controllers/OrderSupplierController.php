<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'lines.*.component_id'       => ['required','exists:components,id'],
            'lines.*.quantity'           => ['required','numeric','min:0.01'],
            'lines.*.price'              => ['required','numeric','min:0'],
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
                $subtotal = $row['quantity'] * $row['price'];

                OrderItem::create([
                    'order_id'     => $order->id,
                    'component_id' => $row['component_id'],
                    'quantity'     => $row['quantity'],
                    'unit_price'   => $row['price'],
                ]);

                $total += $subtotal;
            }

            $order->update(['total' => $total]);
        });

        return response()->json(['success' => true]);
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
