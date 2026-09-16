<?php

namespace App\Http\Controllers;

use App\Enums\ProductionPhase;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $alerts = Alert::visible()
            ->orderBy('is_read')
            ->orderByDesc('triggered_at')
            ->paginate(20);

        $orderIds = $alerts->getCollection()->pluck('payload.order_id')->filter()->unique();

        $piecesToComplete = DB::table('order_items as items')
            ->join('v_order_item_phase_qty as phase_qty', 'phase_qty.order_item_id', '=', 'items.id')
            ->whereIn('items.order_id', $orderIds)
            ->where('phase_qty.phase', '<', ProductionPhase::SHIPPING->value)
            ->where('phase_qty.qty_in_phase', '>', 0)
            ->groupBy('items.order_id')
            ->selectRaw('items.order_id, SUM(phase_qty.qty_in_phase) AS pieces_to_complete')
            ->pluck('pieces_to_complete', 'order_id');

        $deliveryDates = DB::table('orders')
            ->whereIn('id', $orderIds)
            ->pluck('delivery_date', 'id');
        $today = now(config('app.timezone', 'Europe/Rome'))->startOfDay();

        $deliveryMessages = $alerts->getCollection()
            ->whereIn('type', ['delivery_15_days', 'delivery_20_days'])
            ->mapWithKeys(function (Alert $alert) use ($deliveryDates, $today) {
                $orderId = data_get($alert->payload, 'order_id');

                return [$alert->id => $deliveryDates->has($orderId)
                    ? $this->deliveryMessage($alert, $deliveryDates->get($orderId), $today)
                    : $alert->message];
            });

        return view('alerts.index', compact('alerts', 'piecesToComplete', 'deliveryMessages'));
    }

    private function deliveryMessage(Alert $alert, ?string $deliveryDate, Carbon $today): string
    {
        $dateLabel = 'non indicata';
        $relativeText = '';

        if ($deliveryDate) {
            $date = Carbon::parse($deliveryDate, $today->getTimezone())->startOfDay();
            $daysRemaining = (int) $today->diffInDays($date, false);
            $dateLabel = $date->format('d/m/Y').($daysRemaining < 0 ? ' - SCADUTA' : '');
            $relativeText = match (true) {
                $daysRemaining === 0 => 'scade oggi ',
                $daysRemaining === 1 => 'scade domani ',
                $daysRemaining > 1 => 'scade tra '.$daysRemaining.' giorni ',
                default => '',
            };
        }

        // Aggiorna solo il testo mostrato: il messaggio storico resta salvato nell'alert.
        $message = preg_replace(
            '/\s*(?:scade (?:tra \d+ giorn[oi]|oggi|domani)\s*)?\(Consegna prevista:[^)]*\)\.?\s*$/u',
            '',
            $alert->message
        );

        return rtrim($message, ' .').' '.$relativeText.'(Consegna prevista: '.$dateLabel.').';
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
