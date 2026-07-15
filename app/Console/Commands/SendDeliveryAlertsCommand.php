<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Enums\ProductionPhase;
use Illuminate\Support\Facades\Log;

class SendDeliveryAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:delivery-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invia (o logga) alert per ordini a 15 e 20 giorni dalla consegna.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysToAlert = [15, 20];

        $today = now(config('app.timezone', 'Europe/Rome'))->startOfDay();

        $targetDates = collect([15, 20])
            ->map(fn (int $days) => $today->copy()
                ->addDays($days)
                ->toDateString())
            ->all();

        // Troviamo gli ordini non completamente spediti la cui data di consegna
        // dista esattamente 15 o 20 giorni da oggi.
        $orders = Order::whereNotNull('delivery_date')
            ->whereIn('delivery_date', $targetDates)
            ->whereExists(function ($query) {
                $query->select(\DB::raw(1))
                      ->from('v_order_item_phase_qty')
                      ->join('order_items', 'order_items.id', '=', 'v_order_item_phase_qty.order_item_id')
                      ->whereColumn('order_items.order_id', 'orders.id')
                      ->where('v_order_item_phase_qty.phase', '<', ProductionPhase::SHIPPING->value)
                      ->where('v_order_item_phase_qty.qty_in_phase', '>', 0);
            })
            ->with(['customer', 'occasionalCustomer', 'orderNumber'])
            ->get();

        if ($orders->isEmpty()) {
            $this->info("Nessun ordine a 15 o 20 giorni dalla consegna.");
            return;
        }

        foreach ($orders as $order) {
            $deliveryDate = \Carbon\Carbon::parse($order->delivery_date)->startOfDay();
            $daysRounded = (int) $today->diffInDays($deliveryDate);
            
            // Per ora loggiamo, in un sistema reale si invierebbe una Mail o Notifica.
            $orderNo = $order->orderNumber?->number ?? $order->id;
            $customerName = $order->customer?->company ?? $order->occasionalCustomer?->company ?? 'Sconosciuto';
            
            $msg = "ALERT CONSEGNA: L'ordine #{$orderNo} per {$customerName} scade tra {$daysRounded} giorni (Consegna prevista: {$deliveryDate->format('d/m/Y')}).";
            
            $dedupeKey = sprintf(
                'delivery:%d:%s:%d',
                $order->id,
                $deliveryDate->format('Y-m-d'),
                $daysRounded
            );
            
            \App\Models\Alert::firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'type' => "delivery_{$daysRounded}_days",
                    'message' => $msg,
                    'payload' => [
                        'order_id' => $order->id,
                        'order_number' => $orderNo,
                        'delivery_date' => $order->delivery_date->format('Y-m-d'),
                        'days_remaining' => $daysRounded,
                    ],
                    'is_read' => false,
                    'triggered_at' => now(),
                ]
            );

            Log::channel('single')->info($msg);
            $this->info($msg);
        }
        
        $this->info("Alert completati.");
    }
}
