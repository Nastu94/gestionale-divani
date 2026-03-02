<?php

namespace App\Services;

use App\Enums\ProductionPhase;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service: Force Reservation Planner (PRECALCOLO).
 *
 * Scopo:
 * - dato un ordine urgente (order_id) e una lista di componenti mancanti (con quantità),
 *   calcola un piano di copertura in due step:
 *   A) da giacenza libera (stock_levels free)
 *   B) riallocando (rubando) prenotazioni da altri ordini idonei (solo fase INSERTED)
 *
 * NON esegue modifiche DB: è un "what would happen" per la UI/UX.
 *
 * NB: non usa lock; il commit successivo dovrà ricalcolare/verificare dentro transazione.
 */
final class ForceReservationPlanner
{
    /**
     * Precalcola il piano di riallocazione.
     *
     * @param OrderItem $urgentItem           Riga ordine (serve per order_id e delivery_date).
     * @param float     $moveQty              Quantità che si sta tentando di avanzare (solo per log/controlli futuri).
     * @param array<int, array<string, int|float|string>> $missingComponents
     *
     * @return array<string, mixed>
     *  - ok: bool
     *  - message: string|null
     *  - plan: array{
     *      missing: array<int, array{component_id:int, code:string, needed:float, reserved:float, missing:float}>,
     *      from_free: array<int, array<int, array{stock_level_id:int, qty:float}>>, // component_id => [..alloc]
     *      from_donors: array<int, array{donor_order_id:int, donor_delivery_date:string, component_id:int, code:string, stock_reservation_id:int, stock_level_id:int, qty:float}>,
     *      donors_summary: array<int, array{donor_order_id:int, donor_delivery_date:string, total_qty:float}>
     *    }
     */
    public function plan(OrderItem $urgentItem, float $moveQty, array $missingComponents): array
    {
        $urgentItem->loadMissing('order');

        /** @var Order $urgentOrder */
        $urgentOrder = $urgentItem->order;

        Log::info('[ForceReservationPlanner] plan start', [
            'urgent_order_id' => $urgentOrder->id,
            'urgent_item_id'  => $urgentItem->id,
            'move_qty'        => $moveQty,
            'missing_cnt'     => count($missingComponents),
        ]);

        // Normalizziamo il payload in un formato prevedibile.
        $missing = collect($missingComponents)->map(function (array $row): array {
            return [
                'component_id' => (int) $row['component_id'],
                'code'         => (string) $row['code'],
                'needed'       => (float) $row['needed'],
                'reserved'     => (float) $row['reserved'],
                'missing'      => (float) $row['missing'],
            ];
        })->values();

        $fromFree   = []; // component_id => allocations[]
        $fromDonors = []; // allocations list
        $donorSummary = []; // donor_order_id => totals

        foreach ($missing as $row) {
            $componentId = $row['component_id'];
            $code        = $row['code'];
            $remaining   = (float) $row['missing'];

            if ($remaining <= 0) {
                continue;
            }

            /* =============================================================
             * STEP A) Copertura da giacenza libera
             * - free_qty = stock_levels.quantity - SUM(reservations su quel livello)
             * - FIFO: stock_levels.created_at ASC
             *============================================================= */
            $freeLevels = $this->freeStockLevelsForComponent($componentId);

            foreach ($freeLevels as $lvl) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min($remaining, (float) $lvl->free_qty);

                if ($take <= 0) {
                    continue;
                }

                $fromFree[$componentId][] = [
                    'stock_level_id' => (int) $lvl->id,
                    'qty'            => (float) $take,
                ];

                $remaining -= $take;
            }

            /* =============================================================
             * STEP B) Se manca ancora, rialloca rubando da prenotazioni altrui
             * Regole donatori:
             * - non l'ordine urgente
             * - solo ordini con tutte le righe in fase INSERTED
             * - ordinati per delivery_date più lontana (DESC)
             *============================================================= */
            if ($remaining > 0) {
                $donorReservations = $this->donorReservationsForComponent(
                    componentId: $componentId,
                    urgentOrderId: (int) $urgentOrder->id
                );

                foreach ($donorReservations as $res) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $take = min($remaining, (float) $res->quantity);

                    if ($take <= 0) {
                        continue;
                    }

                    $fromDonors[] = [
                        'donor_order_id'       => (int) $res->order_id,
                        'donor_delivery_date'  => (string) $res->delivery_date,
                        'component_id'         => (int) $componentId,
                        'code'                 => (string) $code,
                        'stock_reservation_id' => (int) $res->sr_id,
                        'stock_level_id'       => (int) $res->stock_level_id,
                        'qty'                  => (float) $take,
                    ];

                    // Summary per UI (quanto togliamo complessivamente a ciascun donatore)
                    $key = (int) $res->order_id;
                    if (!isset($donorSummary[$key])) {
                        $donorSummary[$key] = [
                            'donor_order_id'      => (int) $res->order_id,
                            'donor_delivery_date' => (string) $res->delivery_date,
                            'total_qty'           => 0.0,
                        ];
                    }
                    $donorSummary[$key]['total_qty'] += (float) $take;

                    $remaining -= $take;
                }
            }

            // STOP: se dopo A+B non copriamo, blocco.
            if ($remaining > 1e-6) {
                $msg = "Impossibile completare la copertura per il componente {$code}. "
                    . "Mancano ancora " . round($remaining, 4) . " unità dopo la riallocazione.";

                Log::warning('[ForceReservationPlanner] plan NOT OK', [
                    'urgent_order_id' => $urgentOrder->id,
                    'component_id'    => $componentId,
                    'code'            => $code,
                    'remaining'       => $remaining,
                ]);

                return [
                    'ok'      => false,
                    'message' => $msg,
                    'plan'    => null,
                ];
            }
        }

        $plan = [
            'missing'        => $missing->all(),
            'from_free'      => $fromFree,
            'from_donors'    => $fromDonors,
            'donors_summary' => array_values($donorSummary),
        ];

        Log::info('[ForceReservationPlanner] plan OK', [
            'urgent_order_id' => $urgentOrder->id,
            'from_free_cnt'   => collect($fromFree)->flatten(1)->count(),
            'from_donors_cnt' => count($fromDonors),
            'donors_cnt'      => count($donorSummary),
        ]);

        return [
            'ok'      => true,
            'message' => null,
            'plan'    => $plan,
        ];
    }

    /**
     * Ritorna stock_levels con free_qty > 0 per un componente.
     *
     * @param int $componentId
     * @return Collection<int, object>  Oggetti con: id, free_qty
     */
    private function freeStockLevelsForComponent(int $componentId): Collection
    {
        /* -------------------------------------------------------------
         * Calcolo free_qty per ogni stock_level:
         *   free_qty = stock_levels.quantity - SUM(sr.quantity)
         * Usiamo una subquery aggregata per evitare N+1.
         *------------------------------------------------------------- */
        $reservedSub = DB::table('stock_reservations')
            ->selectRaw('stock_level_id, SUM(quantity) as reserved_qty')
            ->groupBy('stock_level_id');

        return DB::table('stock_levels')
            ->leftJoinSub($reservedSub, 'r', 'r.stock_level_id', '=', 'stock_levels.id')
            ->where('stock_levels.component_id', $componentId)
            ->selectRaw('stock_levels.id, (stock_levels.quantity - COALESCE(r.reserved_qty, 0)) as free_qty')
            ->whereRaw('(stock_levels.quantity - COALESCE(r.reserved_qty, 0)) > 0')
            ->orderBy('stock_levels.created_at') // FIFO
            ->get();
    }

    /**
     * Recupera prenotazioni donatrici per un componente.
     * Regola: ordine donatore "rubabile" solo se tutte le sue righe sono in fase INSERTED.
     *
     * @param int $componentId
     * @param int $urgentOrderId
     * @return Collection<int, object> Oggetti con: sr_id, order_id, delivery_date, stock_level_id, quantity
     */
    private function donorReservationsForComponent(int $componentId, int $urgentOrderId): Collection
    {
        /* -------------------------------------------------------------
         * NOTE:
         * - stock_reservations lega solo order_id (non order_item_id)
         * - quindi verifichiamo rubabilità a livello ordine:
         *   NOT EXISTS order_items con current_phase != INSERTED
         *------------------------------------------------------------- */

        return DB::table('stock_reservations as sr')
            ->join('stock_levels as sl', 'sl.id', '=', 'sr.stock_level_id')
            ->join('orders as o', 'o.id', '=', 'sr.order_id')
            ->where('sl.component_id', $componentId)
            ->where('sr.order_id', '!=', $urgentOrderId)
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('order_items as oi')
                    ->whereColumn('oi.order_id', 'sr.order_id')
                    ->where('oi.current_phase', '!=', ProductionPhase::INSERTED->value);
            })
            ->select([
                'sr.id as sr_id',
                'sr.order_id',
                'o.delivery_date',
                'sr.stock_level_id',
                'sr.quantity',
            ])
            ->orderBy('o.delivery_date', 'desc')  // prima ordini più lontani
            ->orderBy('sr.id')
            ->get();
    }
}