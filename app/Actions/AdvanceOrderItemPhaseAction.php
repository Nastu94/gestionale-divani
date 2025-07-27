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

            /* ------------------------------------------------------------------
            | 3 bis ▸ blocco avanzamento se mancano prenotazioni componenti
            |         necessari alla fase di destinazione.
            *----------------------------------------------------------------- */
            if (! $this->isRollback) {

                /** id fase di destinazione */
                $destPhase = $toPhase;           // 1-5

                Log::debug('[AdvanceOrderItemPhaseAction] check reservations', [
                    'destPhase' => $destPhase,
                    'order_id'  => $item->order_id,
                ]);

                /* ① elenca i componenti il cui category → phase = $destPhase */
                $components = $item->product?->components()
                    ->with('category.phaseLinks')               // eager-load
                    ->get()
                    ->filter(fn ($comp) =>                      // tieni solo le categorie
                        $comp->category
                            ->phasesEnum()                     // Collection<ProductionPhase>
                            ->contains(fn ($ph) => $ph->value === $destPhase)
                    );

                Log::debug('[AdvanceOrderItemPhaseAction] components', [
                    'count' => $components->count(),
                    'codes' => $components->pluck('code')->all(),
                    'destPhase' => $destPhase,
                ]);

                foreach ($components as $comp) {

                    /** quantità necessaria per i pezzi che stiamo avanzando */
                    $perPiece   = $comp->pivot->quantity;           // da product_components
                    $neededQty  = $perPiece * $this->quantity;

                    /** prenotazioni esistenti per quest’ordine e componente */
                    $reserved = DB::table('stock_reservations as sr')
                        ->join('stock_levels as sl', 'sl.id', '=', 'sr.stock_level_id')
                        ->where('sr.order_id',    $item->order_id)
                        ->where('sl.component_id',$comp->id)
                        ->sum('sr.quantity'); 

                    Log::debug('[AdvanceOrderItemPhaseAction] reservation check', [
                        'component'   => $comp->code,
                        'needed'      => $neededQty,
                        'reserved'    => $reserved,
                    ]);

                    if ($reserved < $neededQty) {
                        throw ValidationException::withMessages([
                            'stock' => "Mancano prenotazioni per il componente {$comp->code} (necessari $neededQty, disponibili $reserved).",
                        ]);
                    }
                }
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
