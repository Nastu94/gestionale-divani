<?php
/**
 * Observer che, alla creazione di un nuovo evento di fase,
 * ricalcola i campi denormalizzati sulla riga ordine e,
 * di conseguenza, sulla testata ordine.
 *
 * - Aggiorna: current_phase, qty_completed, phase_updated_at
 * - Mantiene: orders.min_phase coerente con le sue righe
 *
 * Tutte le operazioni avvengono in un’unica transazione
 * per evitare race-condition fra batch concorrenti.
 */
namespace App\Observers;

use App\Enums\ProductionPhase;
use App\Models\OrderItemPhaseEvent;
use Illuminate\Support\Facades\DB;

class OrderItemPhaseEventObserver
{
    /**
     * Triggerato dopo INSERT.
     */
    public function created(OrderItemPhaseEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            /** @var \App\Models\OrderItem $item */
            $item = $event->orderItem()->lockForUpdate()->firstOrFail();

            // 1 ▸ ricalcola qty presenti in OGNI fase
            $phaseQty = $item->phaseEvents()           // tutti gli eventi della riga
                ->selectRaw('
                    to_phase   AS phase,
                    SUM(quantity) AS qty_in
                ')
                ->groupBy('phase')
                ->pluck('qty_in', 'phase')             // [phase => qty_in_phase]
                ->mapWithKeys(fn ($qty, $phase) => [(int)$phase => (float)$qty])
                ->all();                               // array: 0-6 => qty

            // 2 ▸ trova la fase minima con qty > 0
            $newCurrent = collect(range(0, 6))
                ->first(fn (int $p) => ($phaseQty[$p] ?? 0) > 0, ProductionPhase::SHIPPING->value);

            // 3 ▸ qty completata = somma di qty in fasi > current_phase
            $qtyCompleted = collect($phaseQty)
                ->filter(fn ($_, $p) => $p > $newCurrent)
                ->sum();

            // 4 ▸ aggiorna la riga ordine
            $item->forceFill([
                'current_phase'   => $newCurrent,
                'qty_completed'   => $qtyCompleted,
                'phase_updated_at'=> now(),
            ])->saveQuietly();

            // 5 ▸ aggiorna la testata ordine (campo min_phase)
            $order = $item->order()->lockForUpdate()->first();
            $min = $order->items()->min('current_phase');   // query singola indicizzata

            $order->forceFill(['min_phase' => $min])->saveQuietly();
        });
    }
}
