<?php
/**
 * ACTION: Avanza (o fa rollback) la produzione di una riga ordine.
 *
 * Incapsula:
 *  • validazioni di business (no salto fase, qty ≤ residuo)
 *  • creazione dell’evento storico
 *  • transazione DB
 *  • delega all’Observer per aggiornare campi denormalizzati
 *
 * @author  Gestionale Divani
 * @license Proprietary
 */

namespace App\Actions;

use App\Enums\ProductionPhase;
use App\Models\OrderItem;
use App\Models\OrderItemPhaseEvent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class AdvanceOrderItemPhaseAction
{
    public function __construct(
        private OrderItem        $item,
        private float            $quantity,      // pezzi da spostare
        private Authenticatable  $user,

        /** Fase di partenza ricevuta dal front-end (KPI selezionata) */
        private ProductionPhase  $fromPhase,

        private bool             $isRollback = false,
        private ?string          $reason     = null,
    ) {}

    /**
     * Esegue l'operazione e restituisce la riga aggiornata.
     */
    public function execute(): OrderItem
    {
        return DB::transaction(function (): OrderItem {
            Log::debug('[AdvanceOrderItemPhaseAction] start', [
                'item'     => $this->item->id,
                'qty'      => $this->quantity,
                'rollback' => $this->isRollback,
                'phase'    => $this->item->current_phase->value,
            ]);

            // 1 ▸ blocco pessimista per evitare race-condition
            $item = OrderItem::whereKey($this->item->id)   // = WHERE id = 342
                ->lockForUpdate()
                ->firstOrFail();

            Log::debug('[AdvanceOrderItemPhaseAction] item locked', [
                'item' => $item->id,
                'phase' => $item->current_phase->value,
            ]);

            // 2 ▸ definisci fase origine/destinazione (front-end = single source of truth)
            $fromPhase = $this->fromPhase->value;                 // es. 1
            $toPhase   = $this->isRollback ? $fromPhase - 1       // rollback ←
                                           : $fromPhase + 1;      // avanzo   →

            Log::debug('[AdvanceOrderItemPhaseAction] fasi', [
                'from' => $fromPhase,
                'to'   => $toPhase,
            ]);

            // 3 ▸ validazioni di business
            if(!$this->user->can('stock.exit')){
                throw ValidationException::withMessages([
                    'auth' => 'Non hai il permesso di avanzare l\'ordine.',
                ]);
            }

            if ($toPhase !== ($this->isRollback ? $fromPhase - 1 : $fromPhase + 1)) {
                throw ValidationException::withMessages([
                    'phase' => 'Non è consentito saltare una fase.',
                ]);
            }

            // quantità residua nella fase origine
            $residue = $item->quantityInPhase($this->fromPhase);

            if ($this->quantity > $residue) {
                throw ValidationException::withMessages([
                    'quantity' => "Quantità richiesta maggiore del residuo ($residue).",
                ]);
            }

            if ($this->isRollback && !$this->user->can('orders.customer.rollback_item_phase')) {
                throw ValidationException::withMessages([
                    'auth' => 'Non hai il permesso di effettuare il rollback.',
                ]);
            }

            // 4 ▸ scrivi l’evento storico
            Log::debug('[AdvanceOrderItemPhaseAction] crea evento', [
                'from' => $fromPhase,
                'to'   => $toPhase,
            ]);

            OrderItemPhaseEvent::create([
                'order_item_id' => $item->id,
                'from_phase'    => $fromPhase,
                'to_phase'      => $toPhase,
                'quantity'      => $this->quantity,
                'changed_by'    => $this->user->id,
                'is_rollback'   => $this->isRollback,
                'reason'        => $this->reason,
            ]);

            // 5 ▸ Observer aggiorna order_items / orders → refresh
            $refreshed = $item->refresh();

            Log::info('[AdvanceOrderItemPhaseAction] commit OK', [
                'current_phase' => $refreshed->current_phase,
                'qty_completed' => $refreshed->qty_completed,
            ]);

            return $refreshed;
        });
    }
}
