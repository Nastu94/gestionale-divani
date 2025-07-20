<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StockReservation;
use App\Models\StockMovement;
use App\Models\PoReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class OrderDeleteService
{
    /**
     * Cancella un OC e restituisce la lista dei PO che erano stati generati
     * ({po_number} già “orfani”).
     *
     * @return Collection<string>   es. ['PO-00012','PO-00013']
     * @throws \Throwable
     */
    public function handle(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {

            Log::info('OC delete – start', ['order_id' => $order->id]);

            /*──────────────────────────────────────
            | PO figli → raccogli numeri PRIMA di modificarli
            *──────────────────────────────────────*/
            $poNumbers = DB::table('order_items as oi')
                ->join('orders        as o',  'o.id',  '=', 'oi.order_id')
                ->join('order_numbers as on', 'on.id', '=', 'o.order_number_id')
                ->whereNotNull('oi.generated_by_order_customer_id')
                ->where   ('oi.generated_by_order_customer_id', $order->id)
                ->pluck('on.number')
                ->unique();

            /*──────────────────────────────────────
            | 1. Stock reservations ➜ UNRESERVE
            *──────────────────────────────────────*/
            StockReservation::where('order_id', $order->id)
                ->lockForUpdate()
                ->each(function ($sr) use ($order) {
                    StockMovement::create([
                        'stock_level_id' => $sr->stock_level_id,
                        'type'           => 'unreserve',
                        'quantity'       => $sr->quantity,
                        'note'           => "Eliminazione OC #{$order->id}",
                    ]);
                    $sr->delete();
                });

            /*──────────────────────────────────────
            | 2. PO reservations ➜ delete
            *──────────────────────────────────────*/
            PoReservation::where('order_customer_id', $order->id)
                ->lockForUpdate()
                ->delete();

            // stacca le PO-line (generated_by_order_customer_id ⇒ null)
            DB::table('order_items')
                ->where('generated_by_order_customer_id', $order->id)
                ->update(['generated_by_order_customer_id' => null]);

            /*──────────────────────────────────────
            | 3. Delete OC + righe
            *──────────────────────────────────────*/
            $order->items()->delete();
            $order->delete();

            Log::info('OC delete – end', ['order_id' => $order->id]);

            return $poNumbers;      // collection di stringhe
        });
    }
}
