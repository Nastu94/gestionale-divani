<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Component;
use App\Services\Traits\InventoryServiceExtensions;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * InventoryService
 * ---------------------------------------------------------------------
 * Calcola la disponibilità di componenti per un Ordine Cliente
 * tenendo conto di:
 *   • stock fisico               (StockLevel)
 *   • prenotazioni altri OC      (stock_reservations)
 *   • quantità in arrivo         (order_items di PO supplier)
 *   • prenotazioni altri OC sui PO (po_reservations)
 *
 * Con forDelivery($date, ?$excludeOcId) puoi escludere dal conteggio
 * le prenotazioni dell'OC stesso (utile in fase di modifica).
 */
class InventoryService
{
    use InventoryServiceExtensions;

    protected CarbonImmutable $deliveryDate;
    protected ?int $excludeOrderId = null;  

    public function __construct(CarbonImmutable $deliveryDate, ?int $excludeOrderId)
    {
        $this->deliveryDate   = $deliveryDate;
        $this->excludeOrderId = $excludeOrderId;
    }

    /** Factory fluente */
    public static function forDelivery($date, ?int $excludeOrderId = null): self
    {
        return new self(
            Carbon::parse($date)->startOfDay()->toImmutable(),
            $excludeOrderId
        );
    }

    /**
     * @param  array<int, array{product_id:int, quantity:float}>  $orderLines
     * @return AvailabilityResult
     */
    public function check(array $orderLines): AvailabilityResult
    {
        Log::info('Inventory check – start', [
            'delivery_date' => $this->deliveryDate->toDateString(),
            'exclude_oc'    => $this->excludeOrderId,
            'lines'         => $orderLines,
        ]);
        /* 1. BOM → fabbisogno componenti */
        $required = $this->explodeBom($orderLines);
        Log::debug('BOM exploded', ['required' => $required]);

        /* 2. Stock fisico disponibile */
        $onHand   = StockLevel::query()
            ->whereIn('component_id', $required->keys())
            ->selectRaw('component_id, SUM(quantity) as qty')
            ->groupBy('component_id')
            ->pluck('qty', 'component_id');

        $reserved = $this->reservedStock($required->keys());

        $available = $onHand->map(fn ($qty,$cid) => $qty - ($reserved[$cid] ?? 0));

        Log::debug('Stock snapshot', [
            'on_hand'  => $onHand,
            'reserved' => $reserved,
            'avail'    => $available,
        ]);

        /* 3. In arrivo */
        $incoming      = $this->incomingPurchase($required->keys());
        $incomingMine  = $this->myIncoming($required->keys());

        Log::debug('Incoming snapshot', [
            'incoming'      => $incoming,
            'incoming_mine' => $incomingMine,
        ]);

        /* 4. Shortage */
        $shortage = collect();
        foreach ($required as $cid => $need) {
            $have = ($available[$cid] ?? 0) + ($incoming[$cid] ?? 0) + ($incomingMine[$cid] ?? 0);

            if ($need > $have + 1e-6) {
                $shortage->push([
                    'component_id' => $cid,
                    'code'         => Component::findOrFail($cid)->code,
                    'description'  => Component::findOrFail($cid)->description,
                    'needed'       => $need,
                    'available'    => $available[$cid]    ?? 0,
                    'incoming'     => $incoming[$cid]     ?? 0,
                    'my_incoming'  => $incomingMine[$cid] ?? 0,
                    'shortage'     => round($need - $have, 4),
                ]);
            }
        }

        Log::info('Inventory check – end', [
            'delivery_date' => $this->deliveryDate->toDateString(),
            'exclude_oc'    => $this->excludeOrderId,
            'ok'            => $shortage->isEmpty(),
            'shortage_cnt'  => $shortage->count(),
            'shortage'      => $shortage,
        ]);

        return new AvailabilityResult($shortage->isEmpty(), $shortage);
    }

    /** esplode righe prodotto → fabbisogno componenti */
    protected function explodeBom(array $orderLines): Collection
    {
        Log::debug('explodeBom – input', $orderLines);
        $needed = collect();
        foreach ($orderLines as $line) {
            $product = Product::with('components')->findOrFail($line['product_id']);
            foreach ($product->components as $component) {
                $cid = $component->id;
                $qty = $line['quantity'] * $component->pivot->quantity;
                $needed[$cid] = ($needed[$cid] ?? 0) + $qty;
            }
        }

        Log::debug('explodeBom – output', ['needed' => $needed]);
        return $needed;
    }

    /** stock prenotato da altri OC */
    protected function reservedStock(Collection $componentIds): Collection
    {
        $data = DB::table('stock_reservations as sr')
            ->join('stock_levels as sl', 'sl.id', '=', 'sr.stock_level_id')
            ->whereIn('sl.component_id', $componentIds)
            ->when($this->excludeOrderId,
                fn ($q) => $q->where('sr.order_id', '!=', $this->excludeOrderId))
            ->selectRaw('sl.component_id, SUM(sr.quantity) as qty')
            ->groupBy('sl.component_id')
            ->pluck('qty', 'sl.component_id');

        Log::debug('reservedStock', ['data' => $data]);
        return $data;
    }

    /**
     * Incoming non riservato da altri OC (qty ancora “libera” sui PO)
     *
     * @return Collection<int,float>  component_id => qty
     */
    protected function incomingPurchase(Collection $componentIds): Collection
    {
        return DB::table('orders as o')
            ->join('order_numbers as onr', 'onr.id', '=', 'o.order_number_id')
            ->join('order_items  as oi',  'oi.order_id', '=', 'o.id')
            ->leftJoin('po_reservations as pr', 'pr.order_item_id', '=', 'oi.id')
            ->where('onr.order_type', 'supplier')
            ->whereNull('o.bill_number')
            ->whereIn('oi.component_id', $componentIds)
            ->whereBetween('o.delivery_date', [now()->startOfDay(), $this->deliveryDate])
            // se sto modificando questo OC, devo escludere le sue stesse prenotazioni
            ->when($this->excludeOrderId, function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('pr.order_customer_id')
                    ->orWhere('pr.order_customer_id', '!=', $this->excludeOrderId);
                });
            })
            ->groupBy('oi.id', 'oi.component_id')                      // una riga per ogni PO-line
            ->selectRaw('
                oi.component_id,
                GREATEST(oi.quantity - COALESCE(SUM(pr.quantity),0), 0) as free
            ')
            ->having('free', '>', 0)                                   // solo se c’è margine
            ->pluck('free', 'oi.component_id');                        // [component_id => qty_free]
    }


    /** quantità prenotata su PO per QUESTO OC (solo informativa) */
    protected function myIncoming(Collection $componentIds): Collection
    {
        if (!$this->excludeOrderId) return collect();

        $data = DB::table('po_reservations as pr')
            ->join('order_items as oi', 'oi.id', '=', 'pr.order_item_id')
            ->join('orders as o',      'o.id',  '=', 'oi.order_id')
            ->join('order_numbers as on','o.order_number_id','=','on.id')
            ->where('on.order_type','supplier')
            ->where('pr.order_customer_id', $this->excludeOrderId)
            ->whereIn('oi.component_id', $componentIds)
            ->whereBetween('o.delivery_date', [now()->startOfDay(), $this->deliveryDate])
            ->selectRaw('oi.component_id, SUM(pr.quantity) as qty')
            ->groupBy('oi.component_id')
            ->pluck('qty', 'oi.component_id');

        Log::debug('myIncoming', ['data' => $data]);
        return $data;
    }

    /**
     * Verifica la disponibilità partendo già da un array
     * [ component_id => qty ]  senza passare per explodeBom().
     * 
     */
    public function checkComponents(array $componentsQty): AvailabilityResult
    {
        // ⬇️  trasformiamo in Collection compatibile con il resto del service
        $required = collect($componentsQty);

        /* Stock fisico */
        $onHand = StockLevel::query()
            ->whereIn('component_id', $required->keys())
            ->selectRaw('component_id, SUM(quantity) as qty')
            ->groupBy('component_id')
            ->pluck('qty', 'component_id');

        $reserved  = $this->reservedStock($required->keys());
        $available = $onHand->map(fn ($q,$cid) => $q - ($reserved[$cid] ?? 0));

        /* In arrivo (altri OC + questo OC) */
        $incoming     = $this->incomingPurchase($required->keys());
        $incomingMine = $this->myIncoming($required->keys());

        /* Shortage */
        $shortage = collect();
        foreach ($required as $cid => $need) {
            $have = ($available[$cid] ?? 0)
                + ($incoming[$cid] ?? 0)
                + ($incomingMine[$cid] ?? 0);

            if ($need > $have + 1e-6) {
                $shortage->push([
                    'component_id' => $cid,
                    'code'         => Component::findOrFail($cid)->code,
                    'description'  => Component::findOrFail($cid)->description,
                    'needed'       => $need,
                    'available'    => $available[$cid]    ?? 0,
                    'incoming'     => $incoming[$cid]     ?? 0,
                    'my_incoming'  => $incomingMine[$cid] ?? 0,
                    'shortage'     => round($need - $have, 4),
                ]);
            }
        }

        Log::debug('checkComponents – result', [
            'delivery_date' => $this->deliveryDate->toDateString(),
            'exclude_oc'    => $this->excludeOrderId,
            'ok'            => $shortage->isEmpty(),
            'shortage_cnt'  => $shortage->count(),
        ]);

        return new AvailabilityResult($shortage->isEmpty(), $shortage);
    }

}