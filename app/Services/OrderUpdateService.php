<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\PoReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderUpdateService
{
    public function handle(Order $order, Collection $payload, ?string $newDate = null): array
    {
        Log::info('OC update – start', [
            'order_id'    => $order->id,
            'payload_cnt' => $payload->count(),
            'new_date'    => $newDate,
        ]);

        /* 1) Stato attuale (key = product:fabric:color) */
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

        /* 2) Incoming (già prezzato e con variabili dal controller) */
        $incoming = $payload->keyBy('key');

        /* 3) Diff per chiave composita */
        $allKeys  = $current->keys()->merge($incoming->keys())->unique();
        $increase = collect();  // delta > 0
        $decrease = collect();  // delta < 0
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

        /* 4) Transazione principale */
        return DB::transaction(function () use ($order, $current, $incoming, $increase, $decrease, $newDate, $changedDate) {

            /* 4.1 Header */
            if ($changedDate) {
                $order->update(['delivery_date' => $newDate]);
            }

            /* 4.2 Delete righe scomparse */
            $incomingKeys = $incoming->keys()->all();
            foreach ($current as $k => $cur) {
                if (!in_array($k, $incomingKeys, true)) {
                    OrderItem::where('id', $cur['order_item_id'])->delete(); // variables: cascade o via relazione
                }
            }

            /* 4.3 Upsert righe + variabili (con campi surcharge/resolved_component) */
            foreach ($incoming as $k => $line) {
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
                            'fabric_id'                 => $line['fabric_id'] ?? null,
                            'color_id'                  => $line['color_id']  ?? null,
                            'resolved_component_id'     => $line['resolved_component_id'] ?? null,
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
                        'fabric_id'                 => $line['fabric_id'] ?? null,
                        'color_id'                  => $line['color_id']  ?? null,
                        'resolved_component_id'     => $line['resolved_component_id'] ?? null,
                        'surcharge_fixed_applied'   => $line['surcharge_fixed_applied'] ?? 0,
                        'surcharge_percent_applied' => $line['surcharge_percent_applied'] ?? 0,
                        'surcharge_total_applied'   => $line['surcharge_total_applied'] ?? 0,
                    ]);
                }
            }

            /* 4.4 Release prenotazioni per diminuzioni */
            if ($decrease->isNotEmpty()) {
                $componentsDec = InventoryService::explodeBomArray($decrease->all());
                $leftovers = InventoryService::forDelivery($order->delivery_date, $order->id)
                    ->releaseReservations($order, $componentsDec);

                if (!empty($leftovers)) {
                    (new ProcurementService())->adjustAfterDecrease($order, $leftovers);
                }
            }

            /* 4.5 Snapshot finale righe → availability iniziale */
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

            // Mappa residuo per componente (partiamo dalla “shortage” calcolata)
            $remaining = collect($inv->shortage)->mapWithKeys(function ($row) {
                return [(int)$row['component_id'] => (float)$row['shortage']];
            })->all();

            /* 4.6 1️⃣ GIACENZA LIBERA → prenota dallo stock fisico prima */
            if (!empty($remaining)) {
                $this->reserveFromStockFirst($order, $inv->shortage, $remaining);
            }

            /* 4.7 2️⃣ ORDINI ESISTENTI CON QTY LIBERE → usa free_qty su qualsiasi PO-line */
            if (!empty($remaining)) {
                $this->allocateFromExistingIncoming(
                    $order,
                    $remaining, // by reference-like logic: la funzione aggiorna il residuo via return
                    Carbon::parse($order->delivery_date)
                );
            }

            /* 4.8 3️⃣ NUOVI ORDINI → per l’eventuale residuo crea nuovi PO (no aumento PO esistenti) */
            $poNumbers = [];
            $stillShort = collect($remaining)
                ->filter(fn ($q) => $q > 1e-9)
                ->map(fn ($q, $cid) => ['component_id' => (int)$cid, 'shortage' => (float)$q])
                ->values();

            if ($stillShort->isNotEmpty()) {
                $shortCol = ProcurementService::buildShortageCollection($stillShort);
                $proc     = ProcurementService::fromShortage($shortCol, $order->id);
                $poNumbers = $proc['po_numbers']->all();

                // dopo la creazione dei PO, il residuo teorico va a zero perché le po_reservations sono state create
                $remaining = [];
            }

            /* 4.9 Totale ordine */
            $total = $order->items->reduce(
                fn ($s, $it) => $s + ((float)$it->quantity * (float)$it->unit_price),
                0.0
            );
            $order->update(['total' => $total]);

            Log::info('OC update – end', [
                'order_id'   => $order->id,
                'total'      => $total,
                'po_numbers' => $poNumbers,
            ]);

            return ['message' => 'Ordine aggiornato', 'po_numbers' => $poNumbers];
        });
    }

    /**
     * 1) Prenota dallo STOCK FISICO prima di tutto.
     * Usa 'available' e 'shortage' dal primo AvailabilityResult.
     * Aggiorna $remaining per componente.
     */
    protected function reserveFromStockFirst(Order $order, Collection $shortageRows, array &$remaining): void
    {
        foreach ($shortageRows as $row) {
            $cid       = (int) $row['component_id'];
            $needLeft  = (float) ($remaining[$cid] ?? 0);
            if ($needLeft <= 0) continue;

            $available = (float) ($row['available'] ?? 0);
            $fromStock = min($available, $needLeft);

            if ($fromStock > 0) {
                // FIFO sui lotti del componente
                StockLevel::where('component_id', $cid)
                    ->orderBy('created_at')
                    ->each(function (StockLevel $sl) use ($order, &$fromStock) {
                        if ($fromStock <= 0) return false;

                        $already = $sl->stockReservations()->sum('quantity');
                        $free    = $sl->quantity - $already;
                        if ($free <= 0) return true;

                        $take = min($free, $fromStock);

                        StockReservation::create([
                            'stock_level_id' => $sl->id,
                            'order_id'       => $order->id,
                            'quantity'       => $take,
                        ]);

                        StockMovement::create([
                            'stock_level_id' => $sl->id,
                            'type'           => 'reserve',
                            'quantity'       => $take,
                            'note'           => "Prenotazione stock per OC #{$order->id} (update)",
                        ]);

                        $fromStock -= $take;
                        return $fromStock > 0;
                    });

                $remaining[$cid] = max(0.0, $needLeft - (float)$row['available']);
            }
        }
    }

    /**
     * 2) Usa quantità libere su PO esistenti (supplier) tra oggi e deliveryDate.
     * Se esiste già una tua reservation su una PO-line → incrementa;
     * altrimenti crea la reservation (nessun aumento quantità delle PO-line).
     * Aggiorna $remaining.
     */
    protected function allocateFromExistingIncoming(Order $order, array &$remaining, Carbon $deliveryDate): void
    {
        foreach ($remaining as $cid => $qtyNeeded) {
            $qtyNeeded = (float) $qtyNeeded;
            if ($qtyNeeded <= 0) continue;

            // PO-line con qty libera (free_qty > 0) entro la data di consegna
            $rows = DB::table('order_items   as oi')
                ->join  ('orders        as o',  'o.id',  '=', 'oi.order_id')
                ->join  ('order_numbers as on', 'on.id', '=', 'o.order_number_id')
                ->leftJoin('po_reservations as pr', 'pr.order_item_id', '=', 'oi.id')
                ->where  ('on.order_type', 'supplier')
                ->whereNull('o.bill_number')
                ->where   ('oi.component_id', $cid)
                ->whereBetween('o.delivery_date', [now()->startOfDay(), $deliveryDate])
                ->groupBy('oi.id', 'oi.quantity', 'oi.component_id', 'o.delivery_date')
                ->selectRaw('
                    oi.id,
                    oi.component_id,
                    GREATEST(oi.quantity - COALESCE(SUM(pr.quantity),0), 0) as free_qty
                ')
                ->having  ('free_qty', '>', 0)
                ->orderBy ('o.delivery_date')  // prima i più vicini
                ->get();

            foreach ($rows as $line) {
                if ($qtyNeeded <= 0) break;

                $take = min((float)$line->free_qty, $qtyNeeded);

                // upsert sicuro: incrementa se esiste, altrimenti crea
                $pr = PoReservation::where('order_item_id', $line->id)
                    ->where('order_customer_id', $order->id)
                    ->lockForUpdate()
                    ->first();

                if ($pr) {
                    $pr->increment('quantity', $take);
                } else {
                    PoReservation::create([
                        'order_item_id'     => (int) $line->id,
                        'order_customer_id' => $order->id,
                        'quantity'          => $take,
                    ]);
                }

                $qtyNeeded -= $take;
            }

            $remaining[$cid] = max(0.0, $qtyNeeded);
        }
    }
}
