<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\AvailabilityResult;

/**
 * InventoryService
 * ---------------------------------------------------------------------
 * • Riceve le righe di un ordine cliente (product_id, quantity)
 * • Esplode la BOM (ProductComponent) in fabbisogno per componente
 * • Calcola giacenza reale       = StockLevel – StockReservation
 * • Calcola giacenza “in arrivo” = PO fornitore in consegna entro la
 *   data dell’ordine cliente (inclusa)
 * • Restituisce un AvailabilityResult
 *
 * Uso:
 *   $result = InventoryService::forDelivery('2025-08-15')
 *               ->check($lines);   // $lines = [['product_id'=>42,'qty'=>3], …]
 *   if (! $result->ok) { … $result->shortage … }
 */
class InventoryService
{
    protected CarbonImmutable $deliveryDate;

    public function __construct(CarbonImmutable $deliveryDate)
    {
        $this->deliveryDate = $deliveryDate;
    }

    /** Factory statica per sintassi fluente */
    /**
     * @param string|\DateTimeInterface $date
     */
    public static function forDelivery($date): self
    {
        return new self(
            Carbon::parse($date)->startOfDay()->toImmutable()
        );
    }

    /**
     * @param  array<int, array{product_id:int, quantity:float}>  $orderLines
     * @return AvailabilityResult
     */
    public function check(array $orderLines): AvailabilityResult
    {
        /* 1. ─── Esplosione BOM → fabbisogno componenti ───────── */
        $required = $this->explodeBom($orderLines);   // Collection keyed by component_id

        /* 2. ─── Stock reale disponibile (on-hand – riservato) ─── */
        $onHand = StockLevel::query()
            ->whereIn('component_id', $required->keys())
            ->selectRaw('component_id, SUM(quantity) as qty')
            ->groupBy('component_id')
            ->pluck('qty', 'component_id');
        
        $available = $onHand;

        /* 2.1 ─── Recupera info componente ────── */
        $info = Component::whereIn('id', $required->keys())
         ->pluck('description', 'id');      // [id => description]
        $codes = Component::whereIn('id', $required->keys())
                ->pluck('code', 'id');             // [id => code]


        /* 3. ─── In arrivo (PO già aperti, consegna ≤ data cliente) ───── */
        $incoming = $this->incomingPurchase($required->keys());

        /* 4. ─── Calcola shortage ─────────────────────────────────────── */
        $shortage = collect();

        foreach ($required as $cid => $need) {
            $have = ($available[$cid] ?? 0) + ($incoming[$cid] ?? 0);

            if ($need > $have + 1e-6) { // tolleranza floating
                $shortage->push([
                    'component_id' => $cid,
                    'code'         => $codes->get($cid, '-'),
                    'description'  => $info->get($cid, ''),
                    'needed'       => $need,
                    'available'    => $available[$cid] ?? 0,
                    'incoming'     => $incoming[$cid] ?? 0,
                    'shortage'     => round($need - $have, 4),
                ]);
            }
        }

        $result = new AvailabilityResult(
            $shortage->isEmpty(),
            $shortage
        );

        Log::info('Inventory check', [
            'delivery_date' => $this->deliveryDate->toDateString(),
            'ok'            => $result->ok,
            'shortage_cnt'  => $shortage->count(),
        ]);

        return $result;
    }

    /**
     * Esplode le righe prodotto in fabbisogno componenti.
     *
     * @param  array<int, array{product_id:int, quantity:float}>  $orderLines
     * @return Collection<int, float>   [component_id => required_qty]
     */
    protected function explodeBom(array $orderLines): Collection
    {
        $needed = collect();

        foreach ($orderLines as $line) {
            $product = Product::with('components')
                        ->findOrFail($line['product_id']);

            foreach ($product->components as $component) {
                $cid  = $component->id;
                $qty  = $line['quantity'] * $component->pivot->quantity;

                $needed[$cid] = ($needed[$cid] ?? 0) + $qty;
            }
        }

        return $needed;
    }

    /**
     * Quantità in arrivo per componenti entro la delivery_date.
     *
     * @param  Collection<int> $componentIds
     * @return Collection<int, float> [component_id => incoming_qty]
     */
    protected function incomingPurchase(Collection $componentIds): Collection
    {
        return DB::table('orders')
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->join('order_numbers', 'orders.order_number_id', '=', 'order_numbers.id')
            ->where('order_numbers.order_type', 'supplier')
            ->whereIn('order_items.component_id', $componentIds)
            ->whereBetween('orders.delivery_date', [now()->startOfDay(), $this->deliveryDate])
            ->selectRaw('order_items.component_id, SUM(order_items.quantity) as qty')
            ->groupBy('order_items.component_id')
            ->pluck('qty', 'order_items.component_id');
    }
}