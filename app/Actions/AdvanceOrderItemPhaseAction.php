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
use Illuminate\Validation\ValidationException;

final readonly class AdvanceOrderItemPhaseAction
{
    public function __construct(
        private OrderItem      $item,
        private float          $quantity,               // pezzi da spostare
        private Authenticatable $user,
        private bool           $isRollback = false,
        private ?string        $reason     = null,      // obbligatoria se rollback
    ) {}

    /**
     * Esegue l'operazione e restituisce la riga aggiornata.
     */
    public function execute(): OrderItem
    {
        return DB::transaction(function (): OrderItem {
            // 1 ▸ blocco pessimista per evitare race-condition
            $item = $this->item->lockForUpdate()->first();

            // 2 ▸ definisci fase origine/destinazione
            $fromPhase = $this->isRollback
                ? $item->current_phase->value          // es. 2
                : $item->current_phase->value;         // es. 1

            $toPhase   = $this->isRollback
                ? $fromPhase - 1                      // rollback → fase precedente
                : $fromPhase + 1;                     // avanzamento → fase successiva

            // 3 ▸ validazioni di business
            if ($toPhase !== ($this->isRollback ? $fromPhase - 1 : $fromPhase + 1)) {
                throw ValidationException::withMessages([
                    'phase' => 'Non è consentito saltare una fase.',
                ]);
            }

            // quantità residua nella fase origine
            $residue = $item->quantityInPhase(ProductionPhase::from($fromPhase));

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
            return $item->refresh();   // contiene current_phase & qty_completed aggiornati
        });
    }
}
