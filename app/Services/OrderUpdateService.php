<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderUpdateService
{
    public function handle(Order $order, Collection $payload, ?string $newDate = null): array
    {
        Log::info('OC update – start', [
            'order_id' => $order->id,
            'payload_cnt' => $payload->count(),
            'new_date' => $newDate,
        ]);

        // 1) stato attuale (key = product:fabric:color)
        $order->load(['items.variable']); // variable = hasOne
        $current = collect();
        foreach ($order->items as $it) {
            $f = $it->variable?->fabric_id ?? null;
            $c = $it->variable?->color_id  ?? null;
            $key = sprintf('%d:%d:%d', $it->product_id, $f ?? 0, $c ?? 0);
            $current[$key] = [
                'order_item_id' => $it->id,
                'product_id'    => $it->product_id,
                'quantity'      => (float) $it->quantity,
                'price'         => (float) $it->unit_price,
                'fabric_id'     => $f,
                'color_id'      => $c,
            ];
        }

        // 2) incoming, già con prezzo e variabili risolti dal controller
        $incoming = $payload->keyBy('key');

        // 3) diff per chiave composita
        $allKeys  = $current->keys()->merge($incoming->keys())->unique();
        $increase = collect();  // righe da aggiungere (delta > 0)
        $decrease = collect();  // righe da togliere (delta < 0)
        foreach ($allKeys as $k) {
            $before = (float) ($current[$k]['quantity'] ?? 0);
            $after  = (float) ($incoming[$k]['quantity'] ?? 0);
            $delta  = $after - $before;
            if ($delta > 0) {
                $increase->push([
                    'product_id' => (int) $incoming[$k]['product_id'],
                    'quantity'   => $delta,
                    'fabric_id'  => $incoming[$k]['fabric_id'] ?? null,
                    'color_id'   => $incoming[$k]['color_id']  ?? null,
                ]);
            } elseif ($delta < 0) {
                $decrease->push([
                    'product_id' => (int) ($current[$k]['product_id'] ?? $incoming[$k]['product_id']),
                    'quantity'   => abs($delta),
                    'fabric_id'  => $current[$k]['fabric_id'] ?? null,
                    'color_id'   => $current[$k]['color_id']  ?? null,
                ]);
            }
        }

        $changedDate = $newDate && $newDate !== $order->delivery_date->format('Y-m-d');
        if ($increase->isEmpty() && $decrease->isEmpty() && ! $changedDate) {
            return ['message' => 'Nessuna modifica'];
        }

        // 4) transazione: upsert righe + variabili; poi availability/PO/reservations
        return DB::transaction(function () use ($order, $current, $incoming, $increase, $decrease, $newDate, $changedDate) {

            /* header */
            if ($changedDate) {
                $order->update(['delivery_date' => $newDate]);
            }

            /* delete righe scomparse */
            $incomingKeys = $incoming->keys()->all();
            foreach ($current as $k => $cur) {
                if (!in_array($k, $incomingKeys, true)) {
                    OrderItem::where('id', $cur['order_item_id'])->delete(); // variables: onDelete cascade o relazione ->delete()
                }
            }

            /* upsert/aggiorna righe e variables */
            foreach ($incoming as $k => $line) {
                // cerca riga esistente stessa chiave (product + variabili)
                $existing = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->where('product_id', $line['product_id'])
                    ->whereHas('variable', function($q) use ($line) {
                        $q->where('fabric_id', $line['fabric_id'] ?? null)
                          ->where('color_id',  $line['color_id']  ?? null);
                    })
                    ->first();

                if ($existing) {
                    $existing->update([
                        'quantity'   => (float) $line['quantity'],
                        'unit_price' => (string) $line['price'],
                    ]);

                    $existing->variable()->updateOrCreate(
                        ['order_item_id' => $existing->id],
                        [
                            'fabric_id'  => $line['fabric_id'] ?? null,
                            'color_id'   => $line['color_id']  ?? null,
                            'resolved_component_id'   => $line['resolved_component_id'] ?? null,
                            'surcharge_fixed_applied'   => $line['surcharge_fixed_applied'] ?? 0,
                            'surcharge_percent_applied' => $line['surcharge_percent_applied'] ?? 0,
                            'surcharge_total_applied'   => $line['surcharge_total_applied'] ?? 0,
                        ]
                    );
                } else {
                    /** @var OrderItem $item */
                    $item = $order->items()->create([
                        'product_id' => (int) $line['product_id'],
                        'quantity'   => (float) $line['quantity'],
                        'unit_price' => (string) $line['price'],
                    ]);

                    $item->variable()->create([
                        'fabric_id'  => $line['fabric_id'] ?? null,
                        'color_id'   => $line['color_id']  ?? null,
                        'resolved_component_id'   => $line['resolved_component_id'] ?? null,
                        'surcharge_fixed_applied'   => $line['surcharge_fixed_applied'] ?? 0,
                        'surcharge_percent_applied' => $line['surcharge_percent_applied'] ?? 0,
                        'surcharge_total_applied'   => $line['surcharge_total_applied'] ?? 0,
                    ]);
                }
            }

            /*  release prenotazioni per diminuzioni */
            if ($decrease->isNotEmpty()) {
                $componentsDec = InventoryService::explodeBomArray($decrease->all());
                $leftovers = InventoryService::forDelivery($order->delivery_date, $order->id)
                    ->releaseReservations($order, $componentsDec);

                // riduci anche eventuali po_reservations se rimane qualcosa da liberare
                if (!empty($leftovers)) {
                    (new ProcurementService())->adjustAfterDecrease($order, $leftovers);
                }
            }

            /*  verifica + (eventuali) nuovi PO per aumenti */
            $poNumbers = [];
            if ($increase->isNotEmpty()) {
                $check = InventoryService::forDelivery($order->delivery_date, $order->id)
                    ->check($increase->values()->all()); // contiene anche fabric/color

                if (! $check->ok) {
                    $created = ProcurementService::fromShortage(
                        ProcurementService::buildShortageCollection($check->shortage),
                        $order->id
                    );
                    $poNumbers = $created['po_numbers']->all();
                }
            }

            /* snapshot finale righe → availability + prenotazioni stock */
            $order->load(['items.variable']);
            $usedLines = $order->items->map(function ($it) {
                return [
                    'product_id' => $it->product_id,
                    'quantity'   => (float) $it->quantity,
                    'fabric_id'  => $it->variable?->fabric_id,
                    'color_id'   => $it->variable?->color_id,
                ];
            })->values()->all();

            $inv = InventoryService::forDelivery($order->delivery_date, $order->id)->check($usedLines);

            // prenota dallo stock fisico la parte disponibile
            foreach ($inv->shortage as $row) {
                $need      = (float) $row['shortage'];    // quantità che manca
                $available = (float) ($row['available'] ?? 0);
                $fromStock = min($available, $need);

                if ($fromStock > 0) {
                    $sl = StockLevel::where('component_id', $row['component_id'])
                        ->orderBy('quantity')
                        ->first();

                    if ($sl) {
                        StockReservation::create([
                            'stock_level_id' => $sl->id,
                            'order_id'       => $order->id,
                            'quantity'       => $fromStock,
                        ]);

                        StockMovement::create([
                            'stock_level_id' => $sl->id,
                            'type'           => 'reserve',
                            'quantity'       => $fromStock,
                            'note'           => "Prenotazione stock per OC #{$order->id} (update)",
                        ]);
                    }
                }
            }

            /* totale ordine */
            $total = $order->items->reduce(fn ($s, $it) => $s + ((float)$it->quantity * (float)$it->unit_price), 0.0);
            $order->update(['total' => $total]);

            Log::info('OC update – end', [
                'order_id'   => $order->id,
                'total'      => $total,
                'po_numbers' => $poNumbers,
            ]);

            return ['message' => 'Ordine aggiornato', 'po_numbers' => $poNumbers];
        });
    }
}
