<?php

namespace App\Services;

use App\Models\Component;
use App\Models\OrderItem;
use App\Models\StockLevelLot;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\OrderItemPhaseEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servizio per la gestione del consumo di lotti di magazzino durante
 * l'avanzamento di fase degli ordini.
 */

class StockLotConsumptionService
{
    /**
     * Consuma la quantità necessaria per l’avanzamento di fase.
     *
     * @param OrderItem $item        riga ordine che sta avanzando
     * @param int       $fromPhase   fase da cui si avanza (0-5)
     * @param float     $qtyPieces   pezzi che passano di fase
     */
    public function consumeForAdvance(OrderItem $item, int $fromPhase, float $qtyPieces): void
    {
        DB::transaction(function () use ($item, $fromPhase, $qtyPieces) {

            /* ── 0. skip se fase “reuse” ---------------------------------- */
            if ($fromPhase > 0 && OrderItemPhaseEvent::query()
                    ->where('order_item_id', $item->id)
                    ->where('from_phase',  $fromPhase + 1)
                    ->where('to_phase',    $fromPhase)
                    ->where('rollback_mode', 'reuse')
                    ->exists()) {
                Log::info('[StockLotConsumptionService] skip – reuse phase', [
                    'item' => $item->id, 'fromPhase' => $fromPhase,
                ]);
                return;                         // ← transazione si chiude subito
            }

            /* ── 1. componenti della fase -------------------------------- */
            $components = $item->product->components()
                ->with(['category.phaseLinks', 'stockLevels.lots'])
                ->get()
                ->filter(fn ($c) =>
                    $c->category->phasesEnum()
                    ->contains(fn ($p) => $p->value === $fromPhase)
                );

            /* ── 2. PRE-CHECK stock_reservations -------------------------- */
            foreach ($components as $c) {
                $need = $c->pivot->quantity * $qtyPieces;

                $reserved = StockReservation::query()
                    ->where('order_id', $item->order_id)
                    ->whereHas('stockLevel',
                        fn ($q) => $q->where('component_id', $c->id))
                    ->sum('quantity');

                if ($reserved + 1e-6 < $need) {
                    // ⬇️ qualsiasi eccezione fa rollback automatico
                    throw ValidationException::withMessages([
                        'stock' => "Componenti insufficienti per {$c->code}: "
                                ."necessari {$need}, giacenza riservata {$reserved}.",
                    ]);
                }
            }

            /* ── 3. consumo lotti & rilascio reservation ------------------ */
            foreach ($components as $c) {

                $needed = $c->pivot->quantity * $qtyPieces;

                $lots = StockLevelLot::query()
                    ->where('stock_level_id', $c->stockLevels()->value('id'))
                    ->where('quantity', '>', 0)
                    ->orderBy('created_at')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($needed <= 0) break;
                    $needed = $this->consumeLot($lot, $needed, $item, $fromPhase);
                }

                if ($needed > 0) {
                    // lancerà eccezione → tutta la transazione si annulla
                    throw ValidationException::withMessages([
                        'stock' => "Consumo lotti interrotto: "
                                .number_format($needed,2,'.','')
                                ." mancanti di {$c->code}.",
                    ]);
                }
            }

            Log::debug('[StockLotConsumptionService] consumo completato', [
                'order_item_id' => $item->id,
                'fromPhase'     => $fromPhase,
                'qtyPieces'     => $qtyPieces,
            ]);
        });
    }

    /* ---------------------------------------------------------------------
     |  Consuma dal singolo lotto                                           |
     *-------------------------------------------------------------------- */
    private function consumeLot(
        StockLevelLot $lot,
        float         $needed,
        OrderItem     $item,
        int           $fromPhase
    ): float {

        // quanto posso prelevare da questo lotto?
        $take = min($lot->quantity, $needed);

        Log::debug('[StockLotConsumptionService] consumo lotto', [
            'lot_id'        => $lot->id,
            'component_id'  => $lot->stockLevel->component_id,
            'take'          => $take,
            'needed'        => $needed,
            'order_item_id' => $item->id,
            'order_id'      => $item->order_id,
        ]);

        /* 1) aggiorna lotto e giacenza ---------------------------------- */
        $lot->decrement('quantity', $take);
        $lot->stockLevel()->decrement('quantity', $take);

        /* 2) scarica la (o le) reservation sullo stesso stock_level ----- */
        $this->releaseReservation(
            $lot->stock_level_id,
            $item->order_id,
            $take
        );

        Log::debug('[StockLotConsumptionService] rilasciata prenotazione', [
            'stock_level_id' => $lot->stock_level_id,
            'order_id'       => $item->order_id,
            'quantity'       => $take,
        ]);

        /* 3) logga il movimento OUT ------------------------------------- */
        StockMovement::create([
            'stock_level_id' => $lot->stock_level_id,
            'type'           => 'out',
            'quantity'       => $take,
            'note'           => "Consumato il lotto {$lot->internal_lot_code} nella fase {$fromPhase} – OC #{$item->order_id}",
        ]);

        Log::debug('[StockLotConsumptionService] movimento OUT registrato', [
            'stock_level_id' => $lot->stock_level_id,
            'quantity'       => $take,
            'order_item_id'  => $item->id,
        ]);

        /* resto da soddisfare dal lotto successivo ---------------------- */
        return $needed - $take;
    }

    /* ---------------------------------------------------------------------
     |  Rilascia la prenotazione collegata allo stesso stock_level         |
     *-------------------------------------------------------------------- */
    private function releaseReservation(
        int   $stockLevelId,
        int   $orderId,
        float $qtyToRelease
    ): void {

        $reservations = StockReservation::query()
            ->where('stock_level_id', $stockLevelId)
            ->where('order_id',       $orderId)
            ->where('quantity', '>',  0)
            ->orderBy('id')               // FIFO puro
            ->lockForUpdate()
            ->get();

        Log::debug('[StockLotConsumptionService] rilascia prenotazioni', [
            'stock_level_id' => $stockLevelId,
            'order_id'       => $orderId,
            'qty_to_release' => $qtyToRelease,
            'reservations'   => $reservations->count(),
        ]);

        foreach ($reservations as $res) {
            if ($qtyToRelease <= 0) {
                break;
            }

            $take = min($res->quantity, $qtyToRelease);
            $res->decrement('quantity', $take);

            // elimina la riga se resta a zero
            if ($res->quantity == 0) {
                $res->delete();
            }

            $qtyToRelease -= $take;
        }

        Log::debug('[StockLotConsumptionService] prenotazioni rilasciate', [
            'stock_level_id' => $stockLevelId,
            'order_id'       => $orderId,
            'qty_released'   => $qtyToRelease,
        ]);
    }
}
