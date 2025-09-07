<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderProductVariable;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderUpdateService
{
    /**
     * Aggiorna un Ordine Cliente applicando differenze a livello componenti.
     *
     * $payload: collection di righe con chiavi:
     *  - product_id:int
     *  - quantity:float
     *  - price:string (DECIMAL)
     *  - fabric_id?:int|null
     *  - color_id?:int|null
     *  - resolved_component_id?:int|null
     *  - surcharge_fixed_applied?:string
     *  - surcharge_percent_applied?:string
     *  - surcharge_total_applied?:string
     */
    public function handle(Order $order, Collection $payload, ?string $newDate = null): array
    {
        Log::info('OC update – start', [
            'order_id' => $order->id,
            'new_date' => $newDate,
            'lines'    => $payload,
        ]);

        // 🔎 1) Snapshot stato ATTUALE (prodotto + variabili) per BOM old
        $order->load(['items.variable']); // variable: hasOne latestOfMany
        $currentLines = $order->items->map(function (OrderItem $it) {
            $v = $it->variable; // può essere null
            return [
                'product_id' => (int) $it->product_id,
                'quantity'   => (float) $it->quantity,
                'fabric_id'  => $v?->fabric_id ? (int)$v->fabric_id : null,
                'color_id'   => $v?->color_id  ? (int)$v->color_id  : null,
            ];
        })->values()->all();

        // 🔎 2) Stato NUOVO dalle righe in ingresso (prodotto + variabili)
        $incomingLines = $payload->map(fn ($l) => [
            'product_id' => (int) $l['product_id'],
            'quantity'   => (float) $l['quantity'],
            'fabric_id'  => $l['fabric_id'] ?? null,
            'color_id'   => $l['color_id']  ?? null,
        ])->values()->all();

        // 🔧 3) Delta a livello COMPONENTI (BOM)
        $oldComponents = InventoryService::explodeBomArray($currentLines); // [cid => qty]
        $newComponents = InventoryService::explodeBomArray($incomingLines);

        $allCids   = collect(array_unique(array_merge(array_keys($oldComponents), array_keys($newComponents))));
        $decrease  = []; // componenti da rilasciare: old > new
        $increase  = []; // componenti da acquisire:  new > old
        foreach ($allCids as $cid) {
            $before = (float)($oldComponents[$cid] ?? 0);
            $after  = (float)($newComponents[$cid] ?? 0);
            if ($after > $before) {
                $increase[$cid] = $after - $before;
            } elseif ($before > $after) {
                $decrease[$cid] = $before - $after;
            }
        }

        $changedDate = $newDate && $newDate !== $order->delivery_date->format('Y-m-d');

        // 🚦 4) Se non cambia nulla e non cambia la data → esci
        if (empty($increase) && empty($decrease) && ! $changedDate) {
            return ['message' => 'Nessuna modifica'];
        }

        // 💾 5) Transazione completa (righe, variabili, prenotazioni)
        return DB::transaction(function () use (
            $order, $payload, $incomingLines, $increase, $decrease, $changedDate, $newDate
        ) {
            // 5.1 header (data)
            if ($changedDate) {
                $order->update(['delivery_date' => $newDate]);
            }
            $delivery = $order->delivery_date->toDateString();

            // 5.2 Rilascio prenotazioni per componenti in diminuzione
            if (!empty($decrease)) {
                // libera stock_reservations a parità di component_id
                $leftovers = InventoryService::forDelivery($delivery, $order->id)
                                ->releaseReservations($order, $decrease);

                // libera anche po_reservations se rimane qualcosa
                if (!empty($leftovers)) {
                    (new ProcurementService)->adjustAfterDecrease($order, $leftovers);
                }
            }

            // 5.3 SOSTITUZIONE RIGHE: per gestire correttamente più righe stesso prodotto con variabili diverse
            //     Puliamo righe e variabili, poi ricreiamo esattamente come da payload.
            //     (Se preferisci, puoi fare un diff riga-per-riga; questa via è robusta e semplice.)
            //    - Elimina prima le variables per evitare orfani se non c'è cascade
            OrderProductVariable::whereIn('order_item_id', $order->items()->pluck('id'))->delete();
            $order->items()->delete();

            foreach ($payload as $l) {
                /** @var OrderItem $item */
                $item = $order->items()->create([
                    'product_id' => (int) $l['product_id'],
                    'quantity'   => (float) $l['quantity'],
                    'unit_price' => (string) $l['price'],    // congelato
                ]);

                // ricrea le variables (anche se null)
                $item->variable()->create([
                    'fabric_id'                 => $l['fabric_id']                 ?? null,
                    'color_id'                  => $l['color_id']                  ?? null,
                    'resolved_component_id'     => $l['resolved_component_id']     ?? null,
                    'surcharge_fixed_applied'   => $l['surcharge_fixed_applied']   ?? '0',
                    'surcharge_percent_applied' => $l['surcharge_percent_applied'] ?? '0',
                    'surcharge_total_applied'   => $l['surcharge_total_applied']   ?? '0',
                ]);
            }

            // 5.4 Disponibilità per l’INTERO ordine aggiornato (come nello store)
            //     Usiamo le nuove righe con variabili per esplodere la BOM corretta.
            $usedLines = collect($incomingLines)->map(fn ($l) => [
                'product_id' => $l['product_id'],
                'quantity'   => $l['quantity'],
                'fabric_id'  => $l['fabric_id'] ?? null,
                'color_id'   => $l['color_id']  ?? null,
            ])->values()->all();

            $invResult = InventoryService::forDelivery($delivery, $order->id)->check($usedLines);

            // 5.4.a Prenota qty “libera” su PO già esistenti
            InventoryServiceExtensions::reserveFreeIncoming(
                $order,
                // NB: qui usiamo la chiave 'shortage' come concordato
                $invResult->shortage->pluck('shortage', 'component_id')->toArray(),
                Carbon::parse($delivery)
            );

            // 5.4.b Ricontrollo e prenotazione stock
            $invResult = InventoryService::forDelivery($delivery, $order->id)->check($usedLines);

            foreach ($invResult->shortage as $row) {
                $need      = (float) $row['shortage'];   // quanto manca ancora
                $availFree = (float) $row['available'];  // stock libero non prenotato

                $fromStock = min($availFree, $need);
                if ($fromStock > 0) {
                    $sl = StockLevel::where('component_id', $row['component_id'])
                            ->orderBy('created_at') // FIFO
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

            // 5.4.c PO per eventuale shortage residuo
            $poNumbers = [];
            if (! $invResult->ok) {
                $shortCol = ProcurementService::buildShortageCollection($invResult->shortage);
                $proc     = ProcurementService::fromShortage($shortCol, $order->id);
                $poNumbers = $proc['po_numbers']->all();
            }

            // 5.5 totale ordine
            $total = $payload->reduce(fn ($s, $l) => $s + ((float)$l['quantity'] * (float)$l['price']), 0.0);
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
