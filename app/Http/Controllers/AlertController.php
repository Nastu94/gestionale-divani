<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $alerts = Alert::orderBy('is_read')
            ->orderByDesc('triggered_at')
            ->paginate(20);

        return view('alerts.index', compact('alerts'));
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
    public function show(Alert $alert)
    {
        abort_unless(auth()->user()->can('alerts.view'), 403);

        $orderId = data_get($alert->payload, 'order_id');

        if (! $orderId) {
            return redirect()
                ->route('alerts.index')
                ->with('error', 'Ordine collegato non disponibile.');
        }

        $order = \App\Models\Order::query()->find($orderId);

        if (! $order) {
            return redirect()
                ->route('alerts.index')
                ->with('error', 'L’ordine collegato non esiste più.');
        }

        abort_unless(auth()->user()->can('orders.customer.view'), 403);

        if (! $alert->is_read) {
            $alert->update(['is_read' => true]);
        }

        return redirect()->route(
            'orders.customer.index',
            ['open_order' => $order->id]
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Alert $alert)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Alert $alert)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Alert $alert)
    {
        //
    }
}
