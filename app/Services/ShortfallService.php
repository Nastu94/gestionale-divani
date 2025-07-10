<?php
// app/Services/ShortfallService.php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemShortfall;
use App\Models\OrderNumber;
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
        /* 1. Carica righe + lotti + stockLevel -------------------- */
        $order->load([
            'items.component',
            'stockLevelLots.stockLevel',
        ]);

        /* 2. Qty ricevute per componente -------------------------- */
        $receivedByComp = $order->stockLevelLots
            ->groupBy(fn ($lot) => $lot->stockLevel->component_id)
            ->map(fn ($g) => $g->sum('quantity'));

        /* 3. Determina mancanze ----------------------------------- */
        $gaps = collect();
        foreach ($order->items as $item) {
            $gap = $item->quantity - $receivedByComp->get($item->component_id, 0);
            if ($gap > 0) {
                $gaps->push(['item' => $item, 'gap' => $gap]);
            }
        }

        if ($gaps->isEmpty()) {
            return null;    // tutto consegnato
        }

        /* 4. Transazione: crea ordine figlio + righe + totale ------ */
        return DB::transaction(function () use ($order, $gaps) {

            /* 4-a Prenota progressivo */
            $num = OrderNumber::reserve('supplier');

            /* 4-b Header ordine figlio */
            $child = Order::create([
                'order_number_id' => $num->id,
                'supplier_id'     => $order->supplier_id,
                'parent_order_id' => $order->id,
                'delivery_date'   => now()->addDays(7),
            ]);

            $total = 0;   // accumula valore economico

            /* 4-c Righe + pivot short-fall */
            foreach ($gaps as $row) {
                $orig  = $row['item'];      // OrderItem originale
                $qty   = $row['gap'];
                $price = $orig->unit_price; // stesso prezzo origine

                /* nuova riga sul figlio */
                $newItem = OrderItem::create([
                    'order_id'     => $child->id,
                    'component_id' => $orig->component_id,
                    'quantity'     => $qty,
                    'unit_price'   => $price,
                ]);

                /* pivot delle mancanze */
                OrderItemShortfall::create([
                    'order_item_id'     => $orig->id,
                    'quantity'          => $qty,
                    'follow_up_item_id' => $newItem->id, // rimuovi se la colonna non esiste
                ]);

                $total += $qty * $price;
            }

            /* 4-d Salva totale ordine figlio */
            $child->total = $total;   // ↙ cambia nome colonna se diverso
            $child->save();

            return $child;
        });
    }
}
