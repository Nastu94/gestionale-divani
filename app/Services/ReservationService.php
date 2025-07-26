<?php

namespace App\Services;

use App\Models\StockLevelLot;
use App\Models\StockReservation;
use App\Models\StockMovement;
use App\Models\PoReservation;
use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Gestisce automaticamente le prenotazioni di magazzino
 * (stock_reservations) quando arrivano o si riducono i lotti
 * collegati a un ordine fornitore.
 *
 *  ✓ attach()  → riserva quantità per gli ordini cliente collegati
 *  ✓ release() → libera prenotazioni se si riduce la quantità del lotto
 */
class ReservationService
{
    /* -----------------------------------------------------------------
     |  Riserva la merce appena registrata su un lotto
     |------------------------------------------------------------------
     | • $lot        : modello StockLevelLot appena creato / incrementato
     | • Copre le quantità presenti in po_reservations
     | • Non crea mai duplicati: se esiste già una reservation, la lascia
     |----------------------------------------------------------------- */
    public function attach(StockLevelLot $lot): void
    {
        $stockLevel  = $lot->stockLevel;                   // relazione già eager-loaded
        $componentId = $stockLevel->component_id;

        // 1. quantità libera sul lotto (giacenza – già riservato)
        $reservedOnLevel = $stockLevel->reservations()->sum('quantity');
        $freeQty         = $stockLevel->quantity - $reservedOnLevel;
        if ($freeQty <= 0) {
            return;                                        // niente da riservare
        }

        // 2. riga PO corrispondente (via pivot ordine → items)
        $order = $lot->orders()->first();                   // ordine fornitore
        if ($order) {
            $poItem = $order->items()
                             ->where('component_id', $componentId)
                             ->with('poReservations')       // eager prenotazioni
                             ->first();
        } else {
            $poItem = null;
        }

        if (! $poItem) {
            return;                                        // sicurezza
        }

        // 3. scorre le prenotazioni cliente e le copre finché c’è freeQty
        foreach ($poItem->poReservations as $poRes) {

            // 3-a quanto resta da prenotare verso quell’OC
            $alreadyReserved = StockReservation::where([
                                'order_id'       => $poRes->order_customer_id,
                                'stock_level_id' => $stockLevel->id,
                              ])->sum('quantity');

            $toReserve = max($poRes->quantity - $alreadyReserved, 0);
            if ($toReserve === 0) {
                continue;                                  // già coperta
            }

            $take = min($toReserve, $freeQty);
            if ($take === 0) {
                break;                                     // lotto esaurito
            }

            // 3-b crea la reservation (idempotente per (order_id,stock_level_id))
            StockReservation::create([
                'stock_level_id' => $stockLevel->id,
                'order_id'       => $poRes->order_customer_id,
                'quantity'       => $take,
            ]);

            // 3-c log movimento RESERVE
            StockMovement::create([
                'stock_level_id' => $stockLevel->id,
                'type'           => 'reserve',
                'quantity'       => $take,
                'note'           => "Prenotazione automatica per OC #{$poRes->order_customer_id}",
            ]);

            // 3-d scala (o elimina) la prenotazione in po_reservations
            if ($take >= $poRes->quantity) {
                $poRes->delete();                          // tutta coperta → riga rimossa
            } else {
                $poRes->decrement('quantity', $take);      // coperta parzialmente
            }

            $freeQty -= $take;
            if ($freeQty === 0) {
                break;
            }
        }
    }

    /* -----------------------------------------------------------------
     |  Libera prenotazioni quando la quantità di un lotto diminuisce
     |-----------------------------------------------------------------
     | • $lot       : StockLevelLot interessato
     | • $deltaAbs  : quantità da liberare (valore assoluto di Δ negativo)
     | • Rilascia partendo dalle reservation più recenti (LIFO)
     | • Se non c’è abbastanza riservato → BusinessRuleException
     |----------------------------------------------------------------- */
    public function release(StockLevelLot $lot, float $deltaAbs): void
    {
        $toFree = $deltaAbs;

        // prenotazioni legate a quel lotto (più recenti prima)
        $reservations = $lot->reservations()->orderByDesc('id')->get();

        foreach ($reservations as $res) {
            if ($toFree <= 0) {
                break;
            }

            $take = min($res->quantity, $toFree);

            // aggiorna o elimina la reservation
            if ($take === $res->quantity) {
                $res->delete();
            } else {
                $res->decrement('quantity', $take);
            }

            // log movimento RELEASE
            StockMovement::create([
                'stock_level_id' => $lot->stock_level_id,
                'type'           => 'unreserve',
                'quantity'       => $take,
                'note'           => 'Rilascio prenotazione automatica (update lotto)',
            ]);

            $toFree -= $take;
        }

        // se resta quantità da liberare → errore di dominio
        if ($toFree > 0) {
            throw new BusinessRuleException('insufficient_reserved');
        }
    }
}
