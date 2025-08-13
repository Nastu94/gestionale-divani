<?php
// app/Observers/OrderItemShortfallObserver.php  (NUOVO FILE)

namespace App\Observers;

use App\Models\OrderItemShortfall;
use Illuminate\Support\Facades\DB;

class OrderItemShortfallObserver
{
    /**
     * All'atto della creazione di uno shortfall su una riga,
     * marca l'ordine padre come 'has_shortfall = true'.
     */
    public function created(OrderItemShortfall $shortfall): void
    {
        // Recupero l'order_id della riga
        $orderId = DB::table('order_items')
            ->where('id', $shortfall->order_item_id)
            ->value('order_id');

        if ($orderId) {
            DB::table('orders')
                ->where('id', $orderId)
                ->update(['has_shortfall' => true]);
        }
    }

    /**
     * (Opzionale) In caso di soft-delete o annullo shortfall NON resettiamo il flag,
     * perché per dominio l’ordine “padre” rimane storico con shortfall creato.
     * Se mai ti servisse l’inverso, aggiungi qui la logica di ricalcolo.
     */
}
