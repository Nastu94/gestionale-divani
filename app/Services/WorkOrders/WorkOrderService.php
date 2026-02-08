<?php

namespace App\Services\WorkOrders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WorkOrder;
use App\Models\WorkOrderLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WorkOrderService
{
    /**
     * Crea un buono per un ORDINE in una FASE (0..5) con SOLO le quantità "nuove"
     * rispetto a quelle già stampate in precedenza per quello stesso ordine+fase.
     */
    public function createForOrderAndPhase(int $orderId, int $phase, $user): WorkOrder
    {
        if ($phase >= 6) {
            throw ValidationException::withMessages(['phase' => 'In spedizione si usa il DDT, non il buono.']);
        }

        return DB::transaction(function () use ($orderId, $phase, $user) {

            $order = Order::query()
                ->with([
                    'orderNumber:id,number',
                    'customer:id,company',
                    'occasionalCustomer:id,company',
                    'items.product:id,name,sku',
                    'items.variable.fabric:id,name,code',
                    'items.variable.color:id,name,code',
                ])
                ->findOrFail($orderId);

            $itemIds = $order->items->pluck('id')->all();

            // 1) Quantità attuale in fase (view v_order_item_phase_qty)
            $qtyInPhase = DB::table('v_order_item_phase_qty')
                ->select('order_item_id', DB::raw('SUM(qty_in_phase) as qty'))
                ->where('phase', $phase)
                ->whereIn('order_item_id', $itemIds)
                ->groupBy('order_item_id')
                ->pluck('qty', 'order_item_id'); // [item_id => qty]

            // 2) Quantità già stampata (somma di tutte le righe buoni precedenti per ordine+fase)
            $alreadyPrinted = WorkOrderLine::query()
                ->whereHas('workOrder', function ($q) use ($orderId, $phase) {
                    $q->where('order_id', $orderId)->where('phase', $phase);
                })
                ->select('order_item_id', DB::raw('SUM(qty) as qty'))
                ->groupBy('order_item_id')
                ->pluck('qty', 'order_item_id'); // [item_id => qty]

            // 3) Costruisci righe delta
            $deltaLines = [];
            foreach ($order->items as $it) {
                $current = (float)($qtyInPhase[$it->id] ?? 0);
                if ($current <= 0) continue;

                $printed = (float)($alreadyPrinted[$it->id] ?? 0);
                $delta   = $current - $printed;

                // Se per qualsiasi motivo (rollback ecc.) delta è <=0, non stampare.
                if ($delta <= 0) continue;

                $fabric = optional(optional($it->variable)->fabric)->name
                    ?? optional(optional($it->variable)->fabric)->code;

                $color  = optional(optional($it->variable)->color)->name
                    ?? optional(optional($it->variable)->color)->code;

                $deltaLines[] = [
                    'order_item_id' => $it->id,
                    'qty'           => $delta,
                    'product_name'  => optional($it->product)->name,
                    'product_sku'   => optional($it->product)->sku,
                    'fabric'        => $fabric,
                    'color'         => $color,
                ];
            }

            if (empty($deltaLines)) {
                throw ValidationException::withMessages([
                    'work_order' => 'Nessuna nuova quantità da preparare per questa fase (già stampato tutto).',
                ]);
            }

            // 4) Numerazione progressiva per anno (lock per evitare doppioni)
            $year = (int) now()->format('Y');

            $nextNumber = (int) (WorkOrder::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->max('number') ?? 0) + 1;

            $wo = WorkOrder::create([
                'order_id'   => $order->id,
                'phase'      => $phase,
                'year'       => $year,
                'number'     => $nextNumber,
                'issued_at'  => now(),
                'created_by' => $user?->id,
            ]);

            foreach ($deltaLines as $l) {
                $wo->lines()->create($l);
            }

            Log::info('[WorkOrderService] creato buono', [
                'work_order_id' => $wo->id,
                'order_id'      => $orderId,
                'phase'         => $phase,
                'lines'         => count($deltaLines),
            ]);

            return $wo;
        });
    }
}
