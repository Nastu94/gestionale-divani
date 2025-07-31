<?php

namespace App\Services;

use App\Models\Component;
use App\Models\OrderItem;
use App\Models\StockLevelLot;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Support\Facades\Log;

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
        // 1️⃣  Elenco dei componenti richiesti dalla fase di PARTENZA
        $components = $item->product->components()
            ->with(['category.phaseLinks', 'stockLevels.lots'])
            ->get()
            ->filter(fn ($comp) =>
                $comp->category
                     ->phasesEnum()                     // Collection<ProductionPhase>
                     ->contains(fn ($ph) => $ph->value === $fromPhase)
            );

        Log::debug('[StockLotConsumptionService] componenti da consumare', [
            'count' => $components->count(),
            'codes' => $components->pluck('code')->all(),
            'fromPhase' => $fromPhase,
        ]);

        foreach ($components as $component) {

            // quantità da prelevare = qty pezzi × qty per pezzo (BOM)
            $needed = $component->pivot->quantity * $qtyPieces;

            // 2️⃣  Giacenza (stock_level) da cui prelevare
            $stockLevel = $component->stockLevels()
                ->orderBy('quantity', 'desc')  // più capiente per sicurezza
                ->lockForUpdate()
                ->firstOrFail();

            // 3️⃣  Lotti FIFO (più vecchi prima)
            $lots = $stockLevel->lots()
                ->where('quantity', '>', 0)
                ->orderBy('created_at')        // FIFO sul timestamp di arrivo
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($needed <= 0) {
                    break;                     // richiesto già soddisfatto
                }

                $needed = $this->consumeLot($lot, $needed, $item, $fromPhase);
            }

            if ($needed > 0) {
                // In teoria non succede: le qty erano state prenotate.
                Log::warning('Consumo lotti – quantità non soddisfatta', [
                    'component' => $component->code,
                    'missing'   => $needed,
                    'order_item_id' => $item->id,
                ]);
            }
        }

        Log::debug('[StockLotConsumptionService] consumo completato', [
            'order_item_id' => $item->id,
            'fromPhase'     => $fromPhase,
            'qtyPieces'     => $qtyPieces,
        ]);
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
            'note'           => "Consumo fase {$fromPhase} – OC #{$item->order_id}",
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
