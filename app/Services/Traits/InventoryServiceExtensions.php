<?php

namespace App\Services\Traits;

use App\Models\Order;
use App\Models\StockReservation;
use App\Models\PoReservation;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;      // 👈
use Illuminate\Support\Facades\DB;
use App\Services\AvailabilityResult;
use App\Services\InventoryService;
use Carbon\CarbonImmutable;
use Carbon\Carbon;


trait InventoryServiceExtensions
{
    /**
     * Calcola il delta tra righe DB e incoming.
     *
     * @param  Collection<int,OrderItem>               $current
     * @param  Collection<int,array{quantity:float}>   $incoming
     * @return array{increase:Collection,decrease:Collection}
     */
    public static function diffLines(Collection $current, Collection $incoming): array
    {
        Log::debug('diffLines – input', [
            'current'  => $current->map->only(['product_id','quantity']),
            'incoming' => $incoming,
        ]);

        $increase = collect();
        $decrease = collect();
        $allKeys  = $current->keys()->merge($incoming->keys())->unique();

        foreach ($allKeys as $pid) {
            $before = $current[$pid]->quantity ?? 0;
            $after  = $incoming[$pid]['quantity'] ?? 0;
            $delta  = $after - $before;

            if ($delta > 0) {
                $increase->push([
                    'product_id' => $pid,
                    'qty_before' => $before,
                    'qty_after'  => $after,
                    'quantity'   => $delta,
                ]);
            } elseif ($delta < 0) {
                $decrease->push([
                    'product_id' => $pid,
                    'qty_before' => $before,
                    'qty_after'  => $after,
                    'quantity'   => abs($delta),
                ]);
            }
        }

        Log::debug('diffLines – output', [
            'increase' => $increase,
            'decrease' => $decrease,
        ]);

        return [$increase, $decrease];
    }

    /** Trasforma righe prodotto in fabbisogno componenti */
    public static function explodeBomArray(array $lines): array
    {
        Log::debug('explodeBomArray – input', ['lines' => $lines]);

        $service = new InventoryService(CarbonImmutable::now(), null);
        $result  = $service->explodeBom($lines)->toArray();

        Log::debug('explodeBomArray – output', ['components' => $result]);
        return $result;
    }

    /**
     * Rimuove le prenotazioni di magazzino e restituisce
     * la quantità che non è stato possibile liberare
     *            ↓           ↓
     * @return array<int,float>  [component_id => qty_non_rilasciata]
     */
    public function releaseReservations(Order $order,
                                        array $componentsQty): array
    {
        $leftToRelease = [];

        foreach ($componentsQty as $cid => $qty) {

            $left = $qty;

            StockReservation::where('order_id', $order->id)
                ->whereHas('stockLevel', fn ($q) => $q->where('component_id',$cid))
                ->orderBy('quantity')
                ->each(function (StockReservation $sr) use (&$left, $order) {

                    $take = min($sr->quantity, $left);

                    $sr->decrement('quantity', $take);
                    if ($sr->quantity <= 0) $sr->delete();

                    StockMovement::create([
                        'stock_level_id' => $sr->stock_level_id,
                        'type'           => 'unreserve',
                        'quantity'       => $take,
                        'note'           => "Rilascio prenotazione OC #{$order->id}",
                    ]);

                    $left -= $take;
                    return $left > 0;      // continua finché serve
                });

            if ($left > 0) {           // non c’erano abbastanza prenotazioni stock
                $leftToRelease[$cid] = $left;
                Log::warning('releaseReservations – richiesta superiore alle prenotazioni', [
                    'order_id'      => $order->id,
                    'component_id'  => $cid,
                    'missing_qty'   => $left,
                ]);
            }
        }

        return $leftToRelease;
    }
    
    /** Prenota stock disponibile o segnala shortage */
    public function reserveOrCheck(Order $order, array $componentsQty): AvailabilityResult
    {
        Log::info('reserveOrCheck – start', [
            'order_id'   => $order->id,
            'components' => $componentsQty,
        ]);

        $check = $this->check(
            collect($componentsQty)->map(fn($q,$cid)=>[
                'product_id'=>$cid,
                'quantity'  =>$q
            ])->values()->all()
        );

        Log::debug('reserveOrCheck – availability', [
            'ok'       => $check->ok,
            'shortage' => $check->shortage,
        ]);

        if ($check->ok) {

            foreach ($componentsQty as $cid => $qty) {
                $left = $qty;

                /* scorri TUTTI i lotti di quel componente                        */
                StockLevel::where('component_id', $cid)
                    ->orderBy('created_at')           // FIFO
                    ->each(function (StockLevel $sl) use ($order, &$left) {

                        // quanto di quel lotto è già prenotato?
                        $already = $sl->stockReservations()->sum('quantity');
                        $free    = $sl->quantity - $already;

                        if ($free <= 0) return $left > 0;   // passa al prossimo lotto

                        $take = min($free, $left);

                        StockReservation::create([
                            'stock_level_id' => $sl->id,
                            'order_id'       => $order->id,
                            'quantity'       => $take,
                        ]);

                        StockMovement::create([
                            'stock_level_id' => $sl->id,
                            'type'           => 'reserve',
                            'quantity'       => $take,
                            'note'           => "Prenotazione stock per OC #{$order->id}",
                        ]);

                        $left -= $take;
                        if ($left <= 0) return false;       // chiude il each()
                    });
            }
        }

        Log::info('reserveOrCheck – end', [
            'order_id' => $order->id,
            'status'   => $check->ok ? 'reserved' : 'shortage',
        ]);

        return $check;
    }

    /**
     * Prenota stock disponibile in PO-line con quantità libera.
     *
     * @param  Order  $order
     * @param  array<int,float>  $componentsQty
     * @param  Carbon  $deliveryDate
     */
    public static function reserveFreeIncoming(Order $order,
                                           array $componentsQty,
                                           Carbon $deliveryDate): void
    {
        foreach ($componentsQty as $cid => $qtyNeeded) {

            // PO-line con qty ancora libera (delivery ≤ data OC)
            $poLines = DB::table('order_items   as oi')
                ->join  ('orders        as o',  'o.id',  '=', 'oi.order_id')
                ->join  ('order_numbers as on', 'on.id', '=', 'o.order_number_id')
                ->leftJoin('po_reservations as pr', 'pr.order_item_id', '=', 'oi.id')
                ->where  ('on.order_type', 'supplier')
                ->whereNull('o.bill_number')
                ->where   ('oi.component_id', $cid)
                ->whereBetween('o.delivery_date', [now()->startOfDay(), $deliveryDate])
                ->groupBy('oi.id', 'oi.quantity', 'oi.component_id', 'o.delivery_date')
                ->selectRaw('oi.id,
                            oi.component_id,
                            GREATEST(oi.quantity - COALESCE(SUM(pr.quantity),0), 0)  as free_qty')
                ->having  ('free_qty', '>', 0)
                ->orderBy ('o.delivery_date')          // prima i più vicini
                ->get();

            foreach ($poLines as $line) {
                if ($qtyNeeded <= 0) break;

                $take = min($line->free_qty, $qtyNeeded);

                PoReservation::create([
                    'order_item_id'      => $line->id,
                    'order_customer_id'  => $order->id,
                    'quantity'           => $take,
                ]);

                $qtyNeeded -= $take;
            }
        }
    }
}
