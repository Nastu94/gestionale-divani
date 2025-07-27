<?php
// app/Services/ShortfallService.php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemShortfall;
use App\Models\OrderNumber;
use App\Models\PoReservation;
use Illuminate\Support\Facades\DB;

/**
 * Genera un unico ordine “short-fall” con le quantità non consegnate.
 */
class ShortfallService
{
    /**
     * @return Order|null  ordine di recupero o null se tutto evaso
     */
    public function capture(Order $order): ?Order
    {
        /* 1. Lazy load relazioni utili ------------------------------- */
        $order->load([
            'items.component',
            'stockLevelLots.stockLevel',
        ]);

        /* 2. Qty ricevute per componente ----------------------------- */
        $receivedByComp = $order->stockLevelLots
            ->groupBy(fn ($lot) => $lot->stockLevel->component_id)
            ->map(fn ($g) => $g->sum('quantity'));

        /* 3. Calcola mancanze e SCARTA quelle già in short-fall ------ */
        $gaps = collect();
        foreach ($order->items as $item) {

            $missing = $item->quantity - $receivedByComp->get($item->component_id, 0);
            if ($missing <= 0) continue;   // nessuna mancanza

            $alreadySF = OrderItemShortfall::where('order_item_id', $item->id)->exists();
            if ($alreadySF) continue;      // ⬅️  salta: ha già short-fall

            $gaps->push(['item' => $item, 'gap' => $missing]);
        }

        if ($gaps->isEmpty()) {
            return null;   // tutto consegnato o già coperto
        }

        /* 4. Transazione: crea ordine figlio + righe + pivot --------- */
        return DB::transaction(function () use ($order, $gaps) {

            /* 4-a numero progressivo */
            $num = OrderNumber::reserve('supplier');

            /* 4-b header figlio */
            $child = Order::create([
                'order_number_id' => $num->id,
                'supplier_id'     => $order->supplier_id,
                'parent_order_id' => $order->id,
                'delivery_date'   => now()->addDays(7),
            ]);

            $total = 0;

            /* 4-c righe + pivot short-fall */
            foreach ($gaps as $row) {

                $orig   = $row['item'];          // OrderItem originale
                $qty    = $row['gap'];
                $price  = $orig->unit_price;

                /* riga sul figlio */
                $newItem = OrderItem::create([
                    'order_id'     => $child->id,
                    'generated_by_order_customer_id' => $orig->generated_by_order_customer_id,
                    'component_id' => $orig->component_id,
                    'quantity'     => $qty,
                    'unit_price'   => $price,
                ]);

                /* pivot short-fall (idempotente) */
                OrderItemShortfall::firstOrCreate(
                    ['order_item_id' => $orig->id],
                    [
                        'quantity'          => $qty,
                        'follow_up_item_id' => $newItem->id,   // se esiste la colonna
                    ]
                );

                /* ——► sposta le prenotazioni cliente  ◄—— */
                foreach ($orig->poReservations as $po) {

                    // copia sul nuovo item
                    PoReservation::create([
                        'order_item_id'      => $newItem->id,
                        'order_customer_id'  => $po->order_customer_id,
                        'quantity'           => $po->quantity,
                    ]);

                    // elimina la vecchia riga
                    $po->delete();
                }

                $total += $qty * $price;
            }

            /* 4-d totale figlio */
            $child->total = $total;
            $child->save();

            return $child;
        });
    }
}
