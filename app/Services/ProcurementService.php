<?php

namespace App\Services;

use App\Models\ComponentSupplier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNumber;
use App\Models\PoReservation;
use App\Services\Traits\ProcurementServiceAdjust;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcurementService
 * ---------------------------------------------------------------------
 * Dato lo shortage (componenti mancanti) crea o aggiorna gli
 * ordini fornitore necessari, suddivisi per supplier e lead-time.
 * Prenota contestualmente la quantità in arrivo su po_reservations.
 *
 *  • delivery_date = today + lead_time_days
 *  • chiave riga unica (order_id, component_id)
 *  • unit_price da ComponentSupplier::last_cost
 *  • generated_by_order_customer_id per tracing
 */
class ProcurementService
{
    use ProcurementServiceAdjust;
    
    /**
     * Crea ordini fornitore a partire dallo shortage di componenti.
     *
     * @param  Collection<int, array{component_id:int, shortage:float}>  $shortage
     * @param  \Carbon\CarbonInterface|string  $deliveryDate
     * @param  int|null  $originOcId
     * @return array{
     *     pos:         Illuminate\Support\Collection<Order>,
     *     po_numbers:  Illuminate\Support\Collection<string>
     * }
     */
    public static function fromShortage(Collection $shortage, int $originOcId): array
    {
        Log::info('Procurement – start', [
            'oc_id'    => $originOcId,
            'rows_cnt' => $shortage->count(),
        ]);

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

                $deliveryDate = now()->startOfDay()
                    ->addDays($grp['lead_time_days'])
                    ->toDateString();

                Log::debug('Proc group', [
                    'supplier_id' => $grp['supplier_id'],
                    'delivery'    => $deliveryDate,
                    'items_cnt'   => count($grp['items']),
                ]);

                /* 2.1   CREA SEMPRE un nuovo OrderNumber + PO supplier  */
                $on = OrderNumber::reserve('supplier');

                $po = Order::create([
                    'order_number_id' => $on->id,
                    'supplier_id'     => $grp['supplier_id'],
                    'delivery_date'   => $deliveryDate,
                    'ordered_at'      => now(),
                    'total'           => 0,
                ]);
                Log::info('PO created', ['po_id' => $po->id, 'number' => $on->number]);

                /* 2.2 righe */
                $totalDelta = 0;
                foreach ($grp['items'] as $it) {

                    $row = OrderItem::create([
                        'order_id'                        => $po->id,
                        'component_id'                    => $it['component_id'],
                        'quantity'                        => $it['shortage'],
                        'unit_price'                      => $it['unit_price'],
                        'generated_by_order_customer_id'  => $originOcId,   // sempre valorizzato
                    ]);

                    if ($row->unit_price == 0 && $it['unit_price'] > 0) {
                        $row->unit_price = $it['unit_price']; $row->save();
                    }
                    
                    $totalDelta += $it['shortage'] * $row->unit_price;

                    Log::debug('PO line upsert', [
                        'po_id'        => $po->id,
                        'component_id' => $it['component_id'],
                        'qty_added'    => $it['shortage'],
                    ]);

                    /* prenotazione merce in arrivo */
                    PoReservation::create([
                        'order_item_id'      => $row->id,
                        'order_customer_id'  => $originOcId,
                        'quantity'           => $it['shortage'],
                    ]);

                }

                if ($totalDelta > 0) $po->increment('total', $totalDelta);

                $poCollection->push($po->load('orderNumber'));

                Log::info('auto_po_created', [
                    'po_id'      => $po->id,
                    'po_number'  => $po->orderNumber->number,
                    'supplier_id'=> $grp['supplier_id'],
                    'lead_time'  => $grp['lead_time_days'],
                    'delta_val'  => $totalDelta,
                    'oc_id'      => $originOcId,
                ]);
            }

            /* risultato */
            $poNums = $poCollection->pluck('orderNumber.number');
            Log::info('Procurement – end', [
                'oc_id'      => $originOcId,
                'po_created' => $poNums,
            ]);

            return [
                'pos'        => $poCollection,
                'po_numbers' => $poNums,
            ];
        });
    }

    /** Helper per costruire la collezione shortage dall’InventoryResult */
    public static function buildShortageCollection(Collection $shortageRaw): Collection
    {
        return $shortageRaw
            // 1️⃣ somma tutte le righe uguali (stesso component_id)
            ->groupBy('component_id')
            ->map(function ($rows) {

                $componentId = (int) $rows->first()['component_id'];
                $qtyNeeded   = $rows->sum('shortage');   // quantità unica e corretta

                // 2️⃣ scegli il fornitore (più rapido → meno costoso)
                $cs = ComponentSupplier::where('component_id', $componentId)
                        ->orderBy('lead_time_days')
                        ->orderBy('last_cost')
                        ->first();

                if (! $cs) {
                    Log::warning('Component senza supplier', ['component_id' => $componentId]);
                    return null;           // verrà filtrato al punto 4
                }

                // 3️⃣ riga arricchita
                return [
                    'component_id'   => $componentId,
                    'shortage'       => $qtyNeeded,
                    'supplier_id'    => $cs->supplier_id,
                    'lead_time_days' => $cs->lead_time_days,
                    'unit_price'     => $cs->last_cost ?? 0,
                ];
            })
            // 4️⃣ elimina eventuali null (componenti senza supplier)
            ->filter()
            // 5️⃣ azzera le chiavi per avere una collection indicizzata 0..n
            ->values();
    }

}
