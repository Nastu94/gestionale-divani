<?php

namespace App\Services;

use App\Models\ComponentSupplier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcurementService
 * ---------------------------------------------------------------------
 * Converte la lista di componenti mancanti (shortage) in ordini
 * fornitore raggruppati per supplier.
 *
 *  • Se esiste già un PO (order_type='supplier') con stessa
 *    delivery_date e supplier, lo riutilizza.
 *  • Le righe (order_items) sono univoche per component_id:
 *    se la riga c’è, incrementa quantity; altrimenti la crea.
 *  • unit_cost pesca da ComponentSupplier::last_cost (può essere 0).
 *  • generated_by_order_customer_id permette la tracciabilità.
 *
 * Uso:
 *   $poCollection = ProcurementService::fromShortage(
 *                      $shortage,       // Collection([{component_id, shortage}])
 *                      '2025-08-15',    // data consegna OC
 *                      $ocId            // id ordine cliente origine
 *                  );
 */
class ProcurementService
{
    /**
     * @param  Collection<int, array{component_id:int, shortage:float}>  $shortage
     * @param  \Carbon\CarbonInterface|string  $deliveryDate
     * @param  int|null  $originOcId
     * @return Collection<Order>  Collezione di PO creati o aggiornati
     */
    public static function fromShortage(Collection $shortage, $deliveryDate, ?int $originOcId = null): Collection
    {
        return DB::transaction(function () use ($shortage, $deliveryDate, $originOcId) {

            /* 1️⃣ group shortage by best supplier */
            $bySupplier = [];
            foreach ($shortage as $row) {
                $best = ComponentSupplier::where('component_id', $row['component_id'])
                        ->orderBy('lead_time_days')
                        ->orderBy('last_cost')
                        ->first();

                if (! $best) {
                    Log::warning('ProcurementService → supplier mancante', $row);
                    continue;
                }

                $bySupplier[$best->supplier_id][] = [
                    'component_id' => $row['component_id'],
                    'quantity'     => $row['shortage'],
                    'unit_cost'    => $best->last_cost ?? 0,
                ];

                Log::info('selected_supplier', [
                    'component_id' => $row['component_id'],
                    'supplier_id'  => $best->supplier_id,
                    'lead_time'    => $best->lead_time_days,
                    'price'        => $best->last_cost,
                ]);
            }

            /* 2️⃣ crea / merge PO per supplier */
            $poCollection = collect();

            foreach ($bySupplier as $supplierId => $items) {

                /* 2.1 cerca ordine esistente (join su order_number) */
                $po = Order::where('supplier_id', $supplierId)
                    ->where('delivery_date', $deliveryDate)
                    ->whereHas('orderNumber', fn ($q) => $q->where('order_type', 'supplier'))
                    ->first();

                /* 2.2 se non esiste, crea OrderNumber + testata PO */
                if (! $po) {
                    $orderNumber = OrderNumber::reserve('supplier');

                    $po = Order::create([
                        'order_number_id' => $orderNumber->id,
                        'supplier_id'     => $supplierId,
                        'delivery_date'   => $deliveryDate,
                        'ordered_at'      => now(),
                        'total'           => 0,
                    ]);
                }

                $totalDelta = 0;

                /* 2.3 righe uniche per component_id */
                foreach ($items as $it) {
                    $orderItem = OrderItem::firstOrCreate(
                        [
                            'order_id'     => $po->id,
                            'component_id' => $it['component_id'],
                        ],
                        [
                            'quantity'     => 0,
                            'unit_price'    => $it['unit_cost'],
                            'generated_by_order_customer_id' => $originOcId,
                        ]
                    );

                    /* aggiorna unit_cost se era 0 */
                    if ($orderItem->unit_cost == 0 && $it['unit_cost'] > 0) {
                        $orderItem->unit_cost = $it['unit_cost'];
                    }

                    $orderItem->increment('quantity', $it['quantity']);
                    $totalDelta += $it['quantity'] * $orderItem->unit_cost;
                }

                /* 2.4 total ordine */
                if ($totalDelta > 0) {
                    $po->increment('total', $totalDelta);
                }

                $poCollection->push($po);

                Log::info('auto_po_created', [
                    'po_id'      => $po->id,
                    'supplier_id'=> $supplierId,
                    'items_cnt'  => count($items),
                    'delta_val'  => $totalDelta,
                    'oc_id'      => $originOcId,
                ]);
            }

            return $poCollection;
        });
    }
}
