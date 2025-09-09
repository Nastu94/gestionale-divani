<?php
/**
 * ACTION: Avanza (o fa rollback) la produzione di una riga ordine.
 *
 * Incapsula:
 *  • validazioni di business (no salto fase, qty ≤ residuo)
 *  • creazione dell’evento storico
 *  • transazione DB
 *  • delega all’Observer per aggiornare campi denormalizzati
 *  • gestione rollback (scrap o reintegro)
 *  • verifica prenotazioni componenti fase destinazione
 *  • scarico lotti (se non fase 0→1)
 *  • gestione errori e rollback
 * 
 * Esegue lo spostamento di una riga ordine da una fase di produzione all'altra.
 *
 * @author  Gestionale Divani
 * @license Proprietary
 */

namespace App\Actions;

use App\Enums\ProductionPhase;
use App\Models\Component;
use App\Models\OrderItem;
use App\Models\OrderItemPhaseEvent;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\StockMovement;
use App\Services\StockLotConsumptionService;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\CarbonImmutable;

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
        private string           $rollbackMode = 'scrap',
    ) {}

    /**
     * Esegue l'operazione e restituisce la riga aggiornata.
     */
    public function execute(): array
    {
        return DB::transaction(function (): array {
            Log::debug('[AdvanceOrderItemPhaseAction] start', [
                'item'     => $this->item->id,
                'qty'      => $this->quantity,
                'rollback' => $this->isRollback,
                'phase'    => $this->item->current_phase->value,
                'mode'     => $this->rollbackMode,
            ]);

            // 1 ▸ blocco pessimista per evitare race-condition
            $item = OrderItem::whereKey($this->item->id)   // = WHERE id = 342
                ->lockForUpdate()
                ->firstOrFail();
            $item->loadMissing('variable', 'product.components.category.phaseLinks');

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
                    'auth' => 'Non hai il permesso di avanzare o fare rollback dell\'ordine.',
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

                $destPhase = $toPhase;   // 1-6

                /* verifica prenotazioni componenti fase destinazione */
                $missing = $this->checkReservations($item, $destPhase);
                if ($missing) {
                    throw ValidationException::withMessages([
                        'stock' => "I seguenti componenti non sono ancora disponibili: "
                                   . implode(', ', $missing) . '.',
                    ]);
                }

                /* scarico fisico lotti (eccetto passaggio fase 0→1) */
                if ($fromPhase > 0) {
                    app(StockLotConsumptionService::class)
                        ->consumeForAdvance(
                            $item,
                            $this->fromPhase->value,   // 👈 enum → int
                            $this->quantity
                        );
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
                'rollback_mode' => $this->isRollback ? $this->rollbackMode : null,
                'reason'        => $this->reason,
            ]);

            /*───────────────────────────────────────────────────────────────────*
            |  Rollback “scrap” – prenotazione giacenza / creazione PO          |
            *───────────────────────────────────────────────────────────────────*/
            $createdPoNumbers = collect();                                     // ← per il flash

            if ($this->isRollback && $this->rollbackMode === 'scrap') {

                /* ■ 1 – fabbisogno componenti della fase DESTINAZIONE ($toPhase) */
                $componentsQty = [];                                           // [comp_id => qty]

                $item->product->load('components.category.phaseLinks');
                foreach ($item->product->components as $comp) {
                    $belongs = $comp->category
                        ->phasesEnum()
                        ->contains(fn ($p) => $p->value === $toPhase);

                    if ($belongs) {
                        $effective = $this->effectiveComponentForItem($item, $comp);
                        $qty = (float) $comp->pivot->quantity * $this->quantity;
                        $componentsQty[$effective->id] = ($componentsQty[$effective->id] ?? 0) + $qty;
                    }
                }

                /* Se non servono componenti (es. fase “Imbottitura” senza BOM) esci. */
                if (empty($componentsQty)) {
                    goto rollback_end;
                }

                /* ■ 2 – verifica disponibilità */
                $delivery   = $item->order->delivery_date
                    ? CarbonImmutable::parse($item->order->delivery_date)
                    : CarbonImmutable::now();

                $inv        = InventoryService::forDelivery($delivery, $item->order_id);
                $availRes   = $inv->checkComponents($componentsQty);
                $createdPoNumbers = collect();

                /* ■ 3 – tutto OK → prenota i lotti FIFO */
                $shortage = [];                                         // [cid => qty_left]

                foreach ($componentsQty as $cid => $need) {

                    $left = $need;

                    StockLevel::where('component_id', $cid)
                        ->orderBy('created_at')       // FIFO
                        ->lockForUpdate()
                        ->each(function (StockLevel $sl) use (&$left, $item) {

                            if ($left <= 0) return false;               // abbiamo già coperto

                            $already = $sl->reservations()->sum('quantity');
                            $free    = max($sl->quantity - $already, 0);
                            if ($free <= 0) return true;                // passa al lotto successivo

                            $take = min($free, $left);

                            StockReservation::create([
                                'stock_level_id' => $sl->id,
                                'order_id'       => $item->order_id,
                                'quantity'       => $take,
                            ]);

                            StockMovement::create([
                                'stock_level_id' => $sl->id,
                                'type'           => 'reserve',
                                'quantity'       => $take,
                                'note'           => "Prenotazione post-rollback OC #{$item->order_id}",
                            ]);

                            $left -= $take;                             // aggiorna residuo
                        });

                    if ($left > 0) {
                        $shortage[$cid] = $left;                        // quantità ancora da coprire
                    }
                }

                /* ■ 4 – se rimane scoperto → genera PO (solo con permesso) ---------------- */
                if (! empty($shortage)) {

                    if (! $this->user->can('orders.supplier.create')) {
                        throw ValidationException::withMessages([
                            'stock' => 'Materiale insufficiente: contatta il commerciale per '
                                    .'creare un ordine fornitore.',
                        ]);
                    }

                    // trasformiamo $shortage in collection compatibile con ProcurementService
                    $shortageColl = collect($shortage)
                        ->map(fn ($q,$cid) => ['component_id' => $cid, 'shortage' => $q])
                        ->values();

                    $shortageColl = ProcurementService::buildShortageCollection($shortageColl);
                    $procResult   = ProcurementService::fromShortage($shortageColl, $item->order_id);

                    $createdPoNumbers = $procResult['po_numbers'] ?? collect();
                }
            }

            rollback_end:

            // 5 ▸ Observer aggiorna order_items / orders → refresh
            $refreshed = $item->refresh();

            Log::info('[AdvanceOrderItemPhaseAction] commit OK', [
                'item' => $refreshed->id,
                'current_phase' => $refreshed->current_phase,
                'qty_completed' => $refreshed->qty_completed,
            ]);

            return [
                'item' => $refreshed,
                'po_numbers' => $createdPoNumbers,
            ];
        });
    }

    /*───────────────────────────────────────────────────────────────────────*
     |  Verifica se esistono prenotazioni sufficienti per la fase destino    |
     *───────────────────────────────────────────────────────────────────────*/
    private function checkReservations(OrderItem $item, int $destPhase): array
    {
        $item->loadMissing('variable', 'product.components.category.phaseLinks');

        $components = $item->product?->components()
            ->with('category.phaseLinks')
            ->get()
            ->filter(fn ($c) =>
                $c->category
                  ->phasesEnum()
                  ->contains(fn ($p) => $p->value === $destPhase)
            );

        $missing = [];

        foreach ($components as $comp) {

            $effective = $this->effectiveComponentForItem($item, $comp);

            $needed   = (float) $comp->pivot->quantity * $this->quantity;

            $reserved = DB::table('stock_reservations as sr')
                ->join('stock_levels as sl', 'sl.id', '=', 'sr.stock_level_id')
                ->where('sr.order_id',     $item->order_id)
                ->where('sl.component_id', $effective->id)
                ->sum('sr.quantity');
            
            Log::debug('[AdvanceOrderItemPhaseAction] check reservations', [
                'component' => $comp->code,
                'needed'    => $needed,
                'reserved'  => $reserved,
            ]);

            if ($reserved + 1e-6 < $needed) {
                $missing[] = $effective->code;
            }
        }

        return $missing;
    }

    /**
     * Restituisce il componente effettivo per l'item, rispettando le variabili.
     * - se la BOM non è variabile → ritorna il placeholder
     * - se esiste resolved_component_id → usa quello
     * - fallback: cerca per category + fabric/color dell'item
     */
    private function effectiveComponentForItem(OrderItem $item, $bomComponent)
    {
        if (!($bomComponent->pivot?->is_variable)) {
            return $bomComponent;
        }

        $resolvedId = $item->variable?->resolved_component_id;
        if ($resolvedId) {
            $x = Component::find($resolvedId);
            if ($x) return $x;
            Log::warning('[AdvanceOrderItemPhaseAction] resolved_component_id non trovato – fallback ricerca FC', [
                'order_item_id' => $item->id,
                'resolved_id'   => $resolvedId,
            ]);
        }

        $fabricId = $item->variable?->fabric_id;
        $colorId  = $item->variable?->color_id;

        $cand = Component::query()
            ->where('category_id', $bomComponent->category_id)
            ->when($fabricId, fn($q) => $q->where('fabric_id', $fabricId))
            ->when($colorId,  fn($q) => $q->where('color_id',  $colorId))
            ->first();

        return $cand ?: $bomComponent;
    }

}
