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
 *  • unit_price pesca da ComponentSupplier::last_cost (può essere 0).
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
    public static function fromShortage(Collection $shortage, int $originOcId): Collection
    {
        return DB::transaction(function () use ($shortage, $originOcId) {

            /* 1️⃣ Raggruppa per supplier + lead_time */
            $byKey = [];
            foreach ($shortage as $row) {
                $key = "{$row['supplier_id']}|{$row['lead_time_days']}";
                $byKey[$key]['supplier_id']       = $row['supplier_id'];
                $byKey[$key]['lead_time_days']    = $row['lead_time_days'];
                $byKey[$key]['items'][]           = $row;
            }

            $poCollection = collect();

            /* 2️⃣ Loop gruppi */
            foreach ($byKey as $grp) {

                $deliveryDate = now()->startOfDay()->addDays($grp['lead_time_days'])->toDateString();

                /* 2.1 cerca PO esistente */
                $po = Order::where('supplier_id', $grp['supplier_id'])
                    ->where('delivery_date', $deliveryDate)
                    ->whereHas('orderNumber', fn($q)=>$q->where('order_type','supplier'))
                    ->first();

                /* 2.2 se non c'è ➜ crea OrderNumber + PO */
                if (!$po) {
                    $on = OrderNumber::reserve('supplier');
                    $po = Order::create([
                        'order_number_id'=> $on->id,
                        'supplier_id'    => $grp['supplier_id'],
                        'delivery_date'  => $deliveryDate,
                        'ordered_at'     => now(),
                        'total'          => 0,
                    ]);
                }

                /* 2.3 righe */
                $totalDelta = 0;
                foreach ($grp['items'] as $it) {
                    $row = OrderItem::firstOrCreate(
                        [
                            'order_id'     => $po->id,
                            'component_id' => $it['component_id'],
                        ],
                        [
                            'quantity'     => 0,
                            'unit_price'    => $it['unit_price'],
                            'generated_by_order_customer_id' => $originOcId,
                        ]
                    );

                    if ($row->unit_price == 0 && $it['unit_price'] > 0) {
                        $row->unit_price = $it['unit_price']; $row->save();
                    }

                    $row->increment('quantity', $it['shortage']);
                    $totalDelta += $it['shortage'] * $row->unit_price;
                }

                if ($totalDelta > 0) $po->increment('total', $totalDelta);

                $poCollection->push($po);
                Log::info('auto_po_created', [
                    'po_id'      => $po->id,
                    'supplier_id'=> $grp['supplier_id'],
                    'lead_time'  => $grp['lead_time_days'],
                    'delta_val'  => $totalDelta,
                    'oc_id'      => $originOcId,
                ]);
            }

            return $poCollection;
        });
    }

    /** Helper per costruire la collezione shortage dall’InventoryResult */
    public static function buildShortageCollection(Collection $shortageRaw): Collection
    {
        /* arricchisce ogni riga con supplier e lead_time */
        return $shortageRaw->map(function ($row) {
            $cs = ComponentSupplier::where('component_id', $row['component_id'])
                    ->orderBy('lead_time_days')
                    ->orderBy('last_cost')
                    ->first();
            return [
                'component_id'   => $row['component_id'],
                'shortage'       => $row['shortage'],
                'supplier_id'    => $cs->supplier_id,
                'lead_time_days' => $cs->lead_time_days,
                'unit_price'      => $cs->last_cost ?? 0,
            ];
        });
    }
}
