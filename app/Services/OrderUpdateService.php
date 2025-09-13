<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\PoReservation;
use App\Services\Traits\InventoryServiceExtensions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderUpdateService
{
    /**
     * Aggiorna un ordine cliente esistente (header + righe + variabili).
     * Gestisce le differenze di quantità (aumento/diminuzione) e
     * l'eventuale variazione della data di consegna.
     *
     * @param Order $order Ordine cliente esistente
     * @param Collection $payload Collezione di righe con chiave composita 'key' = product:fabric:color
     *                            e campi: product_id, quantity, price, fabric_id, color_id,
     *                            resolved_component_id, surcharge_fixed_applied,
     *                            surcharge_percent_applied, surcharge_total_applied
     * @param string|null $newDate Nuova data di consegna (Y-m-d) o null per nessuna modifica
     * @return array ['message' => string, 'po_numbers' => array]
     */
// App\Services\OrderUpdateService.php

public function handle(Order $order, Collection $payload, ?string $newDate = null): array
{
    $t0 = microtime(true);

    Log::info('OC update – start', [
        'order_id'    => $order->id,
        'payload_cnt' => $payload->count(),
        'new_date'    => $newDate,
    ]);

    /* 1) Stato attuale (key = product:fabric:color) */
    $order->load(['items.variable']); // variable = hasOne

    $current = collect();
    foreach ($order->items as $it) {
        $f   = $it->variable?->fabric_id ?? null;
        $c   = $it->variable?->color_id  ?? null;
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
    Log::debug('OC update – current snapshot', [
        'order_id'     => $order->id,
        'current_cnt'  => $current->count(),
        'current_keys' => $current->keys()->values(),
    ]);

    /* 2) Incoming (già prezzato e con variabili dal controller) */
    $incoming = $payload->keyBy('key');
    Log::debug('OC update – incoming snapshot', [
        'order_id'      => $order->id,
        'incoming_cnt'  => $incoming->count(),
        'incoming_keys' => $incoming->keys()->values(),
    ]);

    /* 3) Diff per chiave composita */
    $allKeys  = $current->keys()->merge($incoming->keys())->unique();
    $increase = collect(); // delta > 0
    $decrease = collect(); // delta < 0

    foreach ($allKeys as $k) {
        $before = (float) ($current[$k]['quantity'] ?? 0);
        $after  = (float) ($incoming[$k]['quantity'] ?? 0);
        $delta  = $after - $before;

        if ($delta > 0) {
            $increase->push([
                'product_id' => (int) $incoming[$k]['product_id'],
                'quantity'   => $delta,
                'fabric_id'  => $incoming[$k]['fabric_id'] ?? null,
                'color_id'   => $incoming[$k]['color_id'] ?? null,
            ]);
        } elseif ($delta < 0) {
            $decrease->push([
                'product_id' => (int) ($current[$k]['product_id'] ?? $incoming[$k]['product_id']),
                'quantity'   => abs($delta),
                'fabric_id'  => $current[$k]['fabric_id'] ?? null,
                'color_id'   => $current[$k]['color_id'] ?? null,
            ]);
        }
    }

    $changedDate = $newDate && $newDate !== $order->delivery_date->format('Y-m-d');
    Log::info('OC update – diff computed', [
        'order_id'     => $order->id,
        'increase_cnt' => $increase->count(),
        'decrease_cnt' => $decrease->count(),
        'changed_date' => (bool) $changedDate,
        'elapsed_ms'   => (int) ((microtime(true) - $t0) * 1000),
    ]);

    if ($increase->isEmpty() && $decrease->isEmpty() && !$changedDate) {
        Log::info('OC update – nothing to do', ['order_id' => $order->id]);
        return ['message' => 'Nessuna modifica'];
    }

    try {
        Log::debug('OC update – TX begin', ['order_id' => $order->id]);

        $result = DB::transaction(function () use (
            $order, $current, $incoming, $increase, $decrease, $newDate, $changedDate, $t0
        ) {
            /* 4.1 Header */
            if ($changedDate) {
                Log::debug('OC update – header update (delivery_date)', [
                    'order_id' => $order->id,
                    'from'     => optional($order->delivery_date)->format('Y-m-d'),
                    'to'       => $newDate,
                ]);
                $order->update(['delivery_date' => $newDate]);
            }

            /* 4.2 Delete righe scomparse */
            $incomingKeys = $incoming->keys()->all();
            $deletedCnt   = 0;
            foreach ($current as $k => $cur) {
                if (!in_array($k, $incomingKeys, true)) {
                    $deletedCnt += OrderItem::where('id', $cur['order_item_id'])->delete(); // variables: cascade
                }
            }
            Log::debug('OC update – deleted missing rows', [
                'order_id'   => $order->id,
                'deletedCnt' => $deletedCnt,
            ]);

            /* 4.3 Upsert righe + variabili */
            $upCnt = 0; $insCnt = 0;
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
                        'discount'   => $line['discount'] ?? [],
                    ]);
                    $existing->variable()->updateOrCreate(
                        ['order_item_id' => $existing->id],
                        [
                            'fabric_id'                 => $line['fabric_id'] ?? null,
                            'color_id'                  => $line['color_id'] ?? null,
                            'resolved_component_id'     => $line['resolved_component_id'] ?? null,
                            'surcharge_fixed_applied'   => $line['surcharge_fixed_applied']   ?? 0,
                            'surcharge_percent_applied' => $line['surcharge_percent_applied'] ?? 0,
                            'surcharge_total_applied'   => $line['surcharge_total_applied']   ?? 0,
                        ]
                    );
                    $upCnt++;
                    Log::debug('OC update – row updated', [
                        'order_id'  => $order->id,
                        'key'       => $k,
                        'product_id'=> $line['product_id'],
                        'qty'       => (float) $line['quantity'],
                    ]);
                } else {
                    /** @var OrderItem $item */
                    $item = $order->items()->create([
                        'product_id' => (int) $line['product_id'],
                        'quantity'   => (float) $line['quantity'],
                        'unit_price' => (string) $line['price'],
                        'discount'   => $line['discount'] ?? [],
                    ]);
                    $item->variable()->create([
                        'fabric_id'                 => $line['fabric_id'] ?? null,
                        'color_id'                  => $line['color_id'] ?? null,
                        'resolved_component_id'     => $line['resolved_component_id'] ?? null,
                        'surcharge_fixed_applied'   => $line['surcharge_fixed_applied']   ?? 0,
                        'surcharge_percent_applied' => $line['surcharge_percent_applied'] ?? 0,
                        'surcharge_total_applied'   => $line['surcharge_total_applied']   ?? 0,
                    ]);
                    $insCnt++;
                    Log::debug('OC update – row inserted', [
                        'order_id'  => $order->id,
                        'key'       => $k,
                        'product_id'=> $line['product_id'],
                        'qty'       => (float) $line['quantity'],
                    ]);
                }
            }
            Log::info('OC update – upsert summary', [
                'order_id' => $order->id,
                'updated'  => $upCnt,
                'inserted' => $insCnt,
            ]);

            /* 4.4 Release prenotazioni per diminuzioni */
            if ($decrease->isNotEmpty()) {
                Log::debug('OC update – decrease present, releasing reservations', [
                    'order_id'   => $order->id,
                    'dec_cnt'    => $decrease->count(),
                    'dec_sample' => $decrease->take(3)->values(),
                ]);

                $componentsDec = \App\Services\Traits\InventoryServiceExtensions::explodeBomArray($decrease->all());
                Log::debug('OC update – decrease exploded to components', [
                    'order_id' => $order->id,
                    'comp_cnt' => count($componentsDec),
                    'comp_top' => array_slice($componentsDec, 0, 5, true),
                ]);

                $leftovers = InventoryService::forDelivery($order->delivery_date, $order->id)
                    ->releaseReservations($order, $componentsDec);

                Log::debug('OC update – release leftovers', [
                    'order_id'  => $order->id,
                    'leftovers' => $leftovers,
                ]);

                if (!empty($leftovers)) {
                    (new ProcurementService())->adjustAfterDecrease($order, $leftovers);
                    Log::debug('OC update – PO adjusted after decrease', ['order_id' => $order->id]);
                }
            } else {
                Log::debug('OC update – no decrease, skip release', ['order_id' => $order->id]);
            }

            /* 4.5 Snapshot righe attuali → fabbisogno completo per componente */
            $order->load(['items.variable']);
            $usedLines = $order->items->map(function ($it) {
                return [
                    'product_id' => $it->product_id,
                    'quantity'   => (float) $it->quantity,
                    'fabric_id'  => $it->variable?->fabric_id,
                    'color_id'   => $it->variable?->color_id,
                ];
            })->values()->all();

            Log::debug('OC update – usedLines for coverage', [
                'order_id'  => $order->id,
                'lines_cnt' => count($usedLines),
                'lines'     => $usedLines,
            ]);

            // Fabbisogno per TUTTI i componenti (non solo shortage)
            $required = \App\Services\Traits\InventoryServiceExtensions::explodeBomArray($usedLines);
            $componentIds = array_keys($required);

            // Prenotazioni MIE su STOCK (component_id => qty)
            $myStock = DB::table('stock_reservations as sr')
                ->join('stock_levels as sl', 'sl.id', '=', 'sr.stock_level_id')
                ->where('sr.order_id', $order->id)
                ->whereIn('sl.component_id', $componentIds)
                ->selectRaw('sl.component_id, SUM(sr.quantity) as qty')
                ->groupBy('sl.component_id')
                ->pluck('qty', 'sl.component_id')
                ->map(fn($q) => (float)$q)
                ->toArray();

            // Prenotazioni MIE su PO (component_id => qty) entro la delivery
            $myIncoming = DB::table('po_reservations as pr')
                ->join('order_items as oi', 'oi.id', '=', 'pr.order_item_id')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->join('order_numbers as onr', 'onr.id', '=', 'o.order_number_id')
                ->where('onr.order_type','supplier')
                ->where('pr.order_customer_id', $order->id)
                ->whereIn('oi.component_id', $componentIds)
                ->whereNull('o.bill_number')
                ->whereBetween('o.delivery_date', [now()->startOfDay(), \Carbon\Carbon::parse($order->delivery_date)])
                ->selectRaw('oi.component_id, SUM(pr.quantity) as qty')
                ->groupBy('oi.component_id')
                ->pluck('qty', 'oi.component_id')
                ->map(fn($q) => (float)$q)
                ->toArray();

            // GAP da coprire con azioni (stock → incoming → nuovi PO)
            $remaining = [];
            foreach ($required as $cid => $need) {
                $mineStock = $myStock[$cid]    ?? 0.0;
                $minePO    = $myIncoming[$cid] ?? 0.0;
                $remaining[$cid] = max(0.0, (float)$need - $mineStock - $minePO);
            }

            Log::info('OC update – coverage map', [
                'order_id'    => $order->id,
                'required'    => array_slice($required, 0, 8, true),
                'my_stock'    => array_slice($myStock, 0, 8, true),
                'my_incoming' => array_slice($myIncoming, 0, 8, true),
                'remaining'   => array_slice($remaining, 0, 8, true),
            ]);

            /* 4.6  STOCK fisico → prenota (copre anche componenti senza shortage) */
            if (!empty(array_filter($remaining, fn($q)=>$q>1e-9))) {
                Log::debug('OC update – ENTER reserveFromStock', [
                    'order_id' => $order->id,
                    'remain'   => $remaining,
                ]);
                $this->reserveFromStock($order, $remaining);
                Log::debug('OC update – EXIT reserveFromStock', [
                    'order_id' => $order->id,
                    'remain'   => $remaining,
                ]);
            }

            /* 4.7  PO esistenti → prenota qty libere */
            if (!empty(array_filter($remaining, fn($q)=>$q>1e-9))) {
                Log::debug('OC update – ENTER allocateFromExistingIncoming', [
                    'order_id' => $order->id,
                    'remain'   => $remaining,
                ]);
                $this->allocateFromExistingIncoming(
                    $order,
                    $remaining, // verrà ridotto in place
                    \Carbon\Carbon::parse($order->delivery_date)
                );
                Log::debug('OC update – EXIT allocateFromExistingIncoming', [
                    'order_id' => $order->id,
                    'remain'   => $remaining,
                ]);
            }

            /* 4.8  Nuovi PO per l’eventuale residuo */
            $poNumbers = [];
            $stillShort = collect($remaining)
                ->filter(fn ($q) => $q > 1e-9)
                ->map(fn ($q, $cid) => ['component_id' => (int) $cid, 'shortage' => (float) $q])
                ->values();

            Log::debug('OC update – stillShort (after stock+incoming allocation)', [
                'order_id' => $order->id,
                'cnt'      => $stillShort->count(),
                'head'     => $stillShort->take(8)->values(),
            ]);

            if ($stillShort->isNotEmpty()) {
                Log::info('OC update – creating new POs for residual shortage', [
                    'order_id' => $order->id,
                    'rows'     => $stillShort,
                ]);

                $shortCol  = ProcurementService::buildShortageCollection($stillShort);
                $proc      = ProcurementService::fromShortage($shortCol, $order->id);
                $poNumbers = $proc['po_numbers']->all();

                Log::info('OC update – new POs created', [
                    'order_id'   => $order->id,
                    'po_numbers' => $poNumbers,
                ]);
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
                'elapsed_ms' => (int) ((microtime(true) - $t0) * 1000),
            ]);

            return ['message' => 'Ordine aggiornato', 'po_numbers' => $poNumbers];
        });

        Log::debug('OC update – TX commit', ['order_id' => $order->id]);
        return $result;
    } catch (\Throwable $e) {
        Log::error('OC update – exception', [
            'order_id' => $order->id,
            'error'    => $e->getMessage(),
            'trace'    => substr($e->getTraceAsString(), 0, 2048),
        ]);
        throw $e; // gestore globale/HTTP
    }
}

    /**
     * 1) Prenota dallo STOCK FISICO prima di tutto.
     * Usa 'available' e 'shortage' dal primo AvailabilityResult.
     * Aggiorna $remaining per componente.
     */
// App\Services\OrderUpdateService.php
// Sostituisce il vecchio reserveFromStockFirst(...).
// Prova a coprire "in place" il fabbisogno residuo consultando i lotti e creando StockReservation.
protected function reserveFromStock(Order $order, array &$remaining): void
{
    foreach ($remaining as $cid => $needLeft) {
        $needLeft = (float) $needLeft;
        if ($needLeft <= 0) continue;

        // FIFO sui lotti del componente, con lock per concorrenza
        $levels = StockLevel::query()
            ->where('component_id', (int)$cid)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        $takenTot = 0.0;

        foreach ($levels as $sl) {
            if ($needLeft <= 0) break;

            // qty già prenotata su questo stock level (tutti gli ordini)
            $already = StockReservation::query()
                ->where('stock_level_id', $sl->id)
                ->sum('quantity');

            $free = (float) $sl->quantity - (float) $already;
            if ($free <= 0) continue;

            $take = min($free, $needLeft);
            if ($take <= 0) continue;

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

            $needLeft -= $take;
            $takenTot += $take;
        }

        // aggiorna residuo per il componente
        $remaining[$cid] = max(0.0, (float)$remaining[$cid] - $takenTot);
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
