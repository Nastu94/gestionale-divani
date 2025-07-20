<?php

namespace App\Services\Traits;

use App\Models\Order;
use App\Models\PoReservation;
use Illuminate\Support\Facades\Log;   // 👈

trait ProcurementServiceAdjust
{
    /**
     * Riduce o elimina le righe di PO originate dall'ordine dopo un decremento.
     *
     * @param Order              $order
     * @param array<int,float>   $componentsQty  [component_id => qty_to_release]
     */
    public function adjustAfterDecrease(Order $order, array $componentsQty): void
    {
        Log::info('PO adjust – start', [
            'order_id'   => $order->id,
            'components' => $componentsQty,
        ]);

        foreach ($componentsQty as $cid => $qty) {
            PoReservation::where('order_customer_id', $order->id)
                ->whereHas('orderItem', fn ($q) => $q->where('component_id', $cid))
                ->orderBy('id')
                ->each(function (PoReservation $pr) use (&$qty, $cid, $order) {

                    $take = min($pr->quantity, $qty);
                    $pr->decrement('quantity', $take);

                    Log::debug('PO reservation decrement', [
                        'po_res_id'   => $pr->id,
                        'order_id'    => $order->id,
                        'component_id'=> $cid,
                        'decrement'   => $take,
                        'left_on_row' => $pr->quantity,
                    ]);

                    $qty -= $take;

                    // elimina la riga se a zero
                    if ($pr->quantity <= 0) {
                        $pr->delete();
                        Log::debug('PO reservation deleted', [
                            'po_res_id' => $pr->id,
                            'component_id' => $cid,
                        ]);
                    }

                    // esci dal ciclo each se abbiamo liberato tutto
                    if ($qty <= 0) {
                        return false;   // break
                    }
                });

            // se rimane qty > 0 significa che non c'erano abbastanza prenotazioni
            if ($qty > 0) {
                Log::warning('PO adjust – leftover qty not released', [
                    'order_id'    => $order->id,
                    'component_id'=> $cid,
                    'leftover'    => $qty,
                ]);
            }
        }

        Log::info('PO adjust – end', ['order_id' => $order->id]);
    }
}
