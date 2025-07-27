<?php
/**
 * Aggiorna i campi denormalizzati di order_items e orders
 * ogni volta che viene creato un OrderItemPhaseEvent.
 *
 *  • current_phase  = prima fase che ha ancora qty > 0
 *  • qty_completed  = somma delle qty nelle fasi successive
 *  • min_phase (order) = fase minima tra tutte le sue righe
 *
 * NB: l’Action è già dentro una transazione, quindi NON
 *     ne apriamo un’altra qui: evitiamo dead-lock con
 *     lockForUpdate sulla stessa riga non committata.
 */

namespace App\Observers;

use App\Enums\ProductionPhase;
use App\Models\OrderItemPhaseEvent;
use Illuminate\Support\Facades\Log;

class OrderItemPhaseEventObserver
{
    public function created(OrderItemPhaseEvent $event): void
    {
        Log::debug('[OrderItemPhaseEventObserver] created', [
            'event' => $event->id,
            'item'  => $event->orderItem->id,
        ]);

        /** @var \App\Models\OrderItem $item */
        $item = $event->orderItem()
                      ->lockForUpdate()   // garantisce coerenza se arrivano eventi concorrenti
                      ->firstOrFail();
        
        Log::debug('[OrderItemPhaseEventObserver] item locked', [
            'item' => $item->id,
            'phase' => $item->current_phase->value,
        ]);

        /* ------------------------------------------------------------------
         | 1 ▸ calcola la quantità NETTA presente in ogni fase
         |     (somma in entrata – somma in uscita)
         *----------------------------------------------------------------- */
        $phaseQty = $item->phaseEvents()
            ->selectRaw('to_phase AS phase, SUM(quantity) AS qty_in')
            ->groupBy('phase')
            ->pluck('qty_in', 'phase')          // ← chiave int (0-6)
            ->mapWithKeys(fn ($qty, $phase) => [           // $phase è int
                (int) $phase => (float) $qty
            ])
            ->all();                                   // [0-6] => qty

        Log::debug('[OrderItemPhaseEventObserver] phaseQty', $phaseQty);

        /* ------------------------------------------------------------------
         | 2 ▸ individua la nuova fase corrente
         *----------------------------------------------------------------- */
        $newCurrent = collect(range(0, 6))
            ->first(fn (int $idx) => ($phaseQty[$idx] ?? 0) > 0,
                    ProductionPhase::SHIPPING->value);   // default = 6
        
        Log::debug('[OrderItemPhaseEventObserver] newCurrent', [
            'newCurrent' => $newCurrent,
            'phaseQty'   => $phaseQty,
        ]);

        /* ------------------------------------------------------------------
        | 3 ▸ quantità completata  ► SOLO fase finale (Spedizione, 6)
        *----------------------------------------------------------------- */
        /**
         * Calcoliamo i pezzi “terminati” interrogando l’unica
         * source-of-truth (gli eventi): è il saldo netto presente
         * nella fase SHIPPING. In questo modo qty_completed cresce
         * esclusivamente quando una unità arriva davvero alla fine
         * del flusso produttivo.
         *
         * NB: quantityInPhase() non tocca il DB – usa la relazione
         *     già in memoria – quindi è O(1).
         */
        $qtyCompleted = $item->quantityInPhase(ProductionPhase::SHIPPING);

        Log::debug('[OrderItemPhaseEventObserver] qtyCompleted', [
            'qtyCompleted' => $qtyCompleted,
        ]);

        /* ------------------------------------------------------------------
        | 4 ▸ aggiorna campi denormalizzati (solo qty_completed)
        |     current_phase e phase_updated_at restano invariati / null
        *----------------------------------------------------------------- */
        $item->forceFill([
            'qty_completed' => $qtyCompleted,
        ])->saveQuietly();

        Log::debug('[OrderItemPhaseEventObserver] item updated', [
            'item' => $item->id,
            'current_phase' => $item->current_phase,
            'qty_completed' => $item->qty_completed,
        ]);
        
        /* ------------------------------------------------------------------
         | 5 ▸ aggiorna la testata ordine
         *----------------------------------------------------------------- */
        $order = $item->order()->lockForUpdate()->first();
        $order->forceFill([
            'min_phase' => $order->items()->min('current_phase'),
        ])->saveQuietly();
        Log::debug('[OrderItemPhaseEventObserver] order updated', [
            'order' => $order->id,
            'min_phase' => $order->min_phase,
        ]);
    }
}
