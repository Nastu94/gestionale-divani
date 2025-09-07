<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemShortfall;
use App\Models\OrderNumber;
use App\Models\PoReservation;
use App\Models\ComponentSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Genera un unico ordine “short-fall” con le quantità non consegnate.
 */
class ShortfallService
{
    /**
     * @return Order|null  ordine di recupero o null se tutto evaso
     */
    public function capture(Order $order): ?Order
    {
        /* 1. Lazy load relazioni utili ------------------------------- */
        $order->load([
            'items.component',
            'stockLevelLots.stockLevel',
        ]);

        /* 2. Qty ricevute per componente ----------------------------- */
        $receivedByComp = $order->stockLevelLots
            ->groupBy(fn ($lot) => $lot->stockLevel->component_id)
            ->map(fn ($g) => $g->sum('quantity'));

        /* 3. Calcola mancanze e SCARTA quelle già in short-fall ------ */
        $gaps = collect();
        foreach ($order->items as $item) {

            $missing = $item->quantity - $receivedByComp->get($item->component_id, 0);
            if ($missing <= 0) continue;   // nessuna mancanza

            $alreadySF = OrderItemShortfall::where('order_item_id', $item->id)->exists();
            if ($alreadySF) continue;      // ⬅️  salta: ha già short-fall

            $gaps->push(['item' => $item, 'gap' => $missing]);
        }

        if ($gaps->isEmpty()) {
            return null;   // tutto consegnato o già coperto
        }

        /* 4. Transazione: crea ordine figlio + righe + pivot --------- */
        return DB::transaction(function () use ($order, $gaps) {

            /* 4-a numero progressivo */
            $num = OrderNumber::reserve('supplier');

            /* 4-b header figlio */
            $child = Order::create([
                'order_number_id' => $num->id,
                'supplier_id'     => $order->supplier_id,
                'parent_order_id' => $order->id,
                'delivery_date'   => now()->addDays(7),
            ]);

            $total = 0;

            /* 4-c righe + pivot short-fall */
            foreach ($gaps as $row) {

                $orig   = $row['item'];          // OrderItem originale
                $qty    = $row['gap'];
                $price  = $orig->unit_price;

                /* riga sul figlio */
                $newItem = OrderItem::create([
                    'order_id'     => $child->id,
                    'generated_by_order_customer_id' => $orig->generated_by_order_customer_id,
                    'component_id' => $orig->component_id,
                    'quantity'     => $qty,
                    'unit_price'   => $price,
                ]);

                /* pivot short-fall (idempotente) */
                OrderItemShortfall::firstOrCreate(
                    ['order_item_id' => $orig->id],
                    [
                        'quantity'          => $qty,
                        'follow_up_item_id' => $newItem->id,   // se esiste la colonna
                    ]
                );

                /* ——► sposta le prenotazioni cliente  ◄—— */
                foreach ($orig->poReservations as $po) {

                    // copia sul nuovo item
                    PoReservation::create([
                        'order_item_id'      => $newItem->id,
                        'order_customer_id'  => $po->order_customer_id,
                        'quantity'           => $po->quantity,
                    ]);

                    // elimina la vecchia riga
                    $po->delete();
                }

                $total += $qty * $price;
            }

            /* 4-d totale figlio */
            $child->total = $total;
            $child->save();

            return $child;
        });
    }

    /**
     * Crea ordini short-fall raggruppati per lead_time del componente.
     *
     * @param  \App\Models\Order  $order       Ordine padre (fornitore)
     * @param  bool               $canCreate   true = crea davvero; false = solo segnala “needed”
     * @return array{
     *   needed: bool,
     *   created: bool,
     *   blocked: ?string,
     *   orders: array<int, array{id:int, number:string|null, delivery_date:?string, lead_time_days:int}>,
     *   groups: array<int, array{lead_time_days:int, delivery_date:string, lines_count:int, total_gap:float}>
     * }
     */
    public function captureGrouped(Order $order, bool $canCreate = true): array
    {
        // 1) Carica relazioni necessarie
        $order->load(['items.component', 'stockLevelLots.stockLevel']);

        // 2) Quantità ricevute per componente
        $receivedByComp = $order->stockLevelLots
            ->groupBy(fn ($lot) => $lot->stockLevel->component_id)
            ->map(fn ($g) => $g->sum('quantity'));

        // 3) Mappa lead_time per (component_id, supplier_id corrente)
        $componentIds = $order->items->pluck('component_id')->filter()->unique()->values();
        $leadByComp = ComponentSupplier::query()
            ->whereIn('component_id', $componentIds)
            ->where('supplier_id', $order->supplier_id)
            ->pluck('lead_time_days', 'component_id'); // [component_id => lead_time_days]

        // 4) Calcola gap per riga, scartando quelle già in shortfall
        $gaps = [];
        foreach ($order->items as $item) {
            $missing = (float)$item->quantity - (float)($receivedByComp[$item->component_id] ?? 0);
            if ($missing <= 0) {
                continue;
            }
            $alreadySF = OrderItemShortfall::where('order_item_id', $item->id)->exists();
            if ($alreadySF) {
                continue;
            }

            // lead time dalla pivot (fallback 7 se mancante o <=0)
            $lead = (int)($leadByComp[$item->component_id] ?? 7);
            if ($lead <= 0) { $lead = 7; }

            $gaps[] = ['item' => $item, 'gap' => $missing, 'lead' => $lead];
        }

        // Nessuna mancanza → niente da fare
        if (empty($gaps)) {
            return [
                'needed'  => false,
                'created' => false,
                'blocked' => null,
                'orders'  => [],
                'groups'  => [],
            ];
        }

        // 5) Raggruppa per lead_time_days
        $groups = [];
        foreach ($gaps as $row) {
            $groups[$row['lead']][] = $row;
        }

        // Riassunto gruppi (sempre utile anche se non creiamo)
        $groupsSummary = [];
        foreach ($groups as $lead => $arr) {
            $groupsSummary[] = [
                'lead_time_days' => (int)$lead,
                'delivery_date'  => now()->addDays((int)$lead)->toDateString(),
                'lines_count'    => count($arr),
                'total_gap'      => array_reduce($arr, fn($t, $x) => $t + (float)$x['gap'], 0.0),
                'componente'     => $arr[0]['item']->component_id ?? null,
            ];
        }

        // 6) Se non si hanno i permessi per creare → segnala soltanto
        if (!$canCreate) {
            return [
                'needed'  => true,
                'created' => false,
                'blocked' => 'no_permission',
                'orders'  => [],
                'groups'  => $groupsSummary,
            ];
        }

        // 7) Crea 1 ordine figlio per ciascun gruppo di lead time
        $createdOrders = [];

        DB::transaction(function () use ($order, $groups, &$createdOrders) {

            foreach ($groups as $lead => $rows) {
                // 7-a prenota numero e crea header figlio
                $num = OrderNumber::reserve('supplier');

                $child = Order::create([
                    'order_number_id' => $num->id,
                    'supplier_id'     => $order->supplier_id,
                    'parent_order_id' => $order->id,
                    'delivery_date'   => now()->addDays((int)$lead),
                ]);

                $total = 0.0;

                // 7-b crea righe figlio + pivot shortfall + spostamento po_reservations
                foreach ($rows as $row) {
                    /** @var \App\Models\OrderItem $orig */
                    $orig  = $row['item'];
                    $qty   = (float)$row['gap'];
                    $price = (float)$orig->unit_price;

                    $newItem = OrderItem::create([
                        'order_id'     => $child->id,
                        'generated_by_order_customer_id' => $orig->generated_by_order_customer_id,
                        'component_id' => $orig->component_id,
                        'quantity'     => $qty,
                        'unit_price'   => $price,
                    ]);

                    OrderItemShortfall::firstOrCreate(
                        ['order_item_id' => $orig->id],
                        [
                            'quantity'          => $qty,
                            'follow_up_item_id' => $newItem->id, // se la colonna esiste
                        ]
                    );

                    // Sposta po_reservations dal padre al figlio
                    foreach ($orig->poReservations as $po) {
                        PoReservation::create([
                            'order_item_id'     => $newItem->id,
                            'order_customer_id' => $po->order_customer_id,
                            'quantity'          => $po->quantity,
                        ]);
                        $po->delete();
                    }

                    $total += $qty * $price;
                }

                // 7-c totale ordine figlio
                $child->total = $total;
                $child->save();

                $createdOrders[] = [
                    'id'             => $child->id,
                    'number'         => $child->number, // accessor nel modello Order
                    'delivery_date'  => optional($child->delivery_date)->toDateString(),
                    'lead_time_days' => (int)$lead,
                ];
            }
        });

        return [
            'needed'  => true,
            'created' => count($createdOrders) > 0,
            'blocked' => null,
            'orders'  => $createdOrders,
            'groups'  => $groupsSummary,
        ];
    }

    /** TRUE se l'ordine ha almeno una riga con shortfall già creato. */
    public function hasAnyShortfall(Order $order): bool
    {
        return DB::table('order_items as oi')
            ->join('order_item_shortfalls as ois', 'ois.order_item_id', '=', 'oi.id')
            ->where('oi.order_id', $order->id)
            // ->whereNull('ois.deleted_at') // se soft-delete
            ->exists();
    }

    /**
     * Verifica se per l'ordine fornitore passato esistono mancanze che
     * produrrebbero righe/quantità in caso di cattura shortfall.
     * Usa captureGrouped($order, false) per NON creare nulla (dry-run).
     */
    public function isShortfallNeeded(Order $order): bool
    {
        if ($this->hasAnyShortfall($order)) {
            return false; // ordini "padre" con shortfall già creato: niente bottone
        }

        $res    = $this->captureGrouped($order, false); // dry-run, non crea nulla
        $needed = (bool)($res['needed'] ?? false);

        if (!$needed && !empty($res['groups']) && is_iterable($res['groups'])) {
            $gap = 0.0;
            foreach ($res['groups'] as $g) { $gap += (float)($g['total_gap'] ?? 0); }
            $needed = $gap > 0;
        }
        return $needed;
    }
}
