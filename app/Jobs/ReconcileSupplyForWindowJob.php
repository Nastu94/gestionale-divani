<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SupplyRun;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\PoReservation;
use App\Services\InventoryService;
use App\Services\AvailabilityResult;
use App\Services\ProcurementService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Job settimanale di riconciliazione coperture (stock/PO) e generazione PO cumulativi.
 *
 * Flusso:
 *  1) Seleziona ordini confermati (status=1) con delivery ∈ [start, end].
 *  2) Salta quelli già coperti al 100% (stock_reservations + po_reservations).
 *  3) Prenota da stock (FIFO), poi da PO esistenti (ETA ≤ delivery_date).
 *  4) Aggrega lo shortfall residuo e crea PO cumulativi via ProcurementService.
 *  5) Subito dopo, rialloca dai nuovi PO verso OGNI ordine cliente (crea po_reservations per-OC).
 *  6) Consolida generated_by_order_customer_id sulle righe PO mono-OC.
 *  7) Aggiorna telemetria (incl. orders_touched) e applica retention (ultimi N run).
 *
 * Note:
 *  - PHP 8.4 / Laravel 12
 *  - Log strutturati con trace_id per correlazione.
 */
class ReconcileSupplyForWindowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var CarbonImmutable Inizio finestra (inclusa) */
    protected CarbonImmutable $start;

    /** @var CarbonImmutable Fine finestra (inclusa) */
    protected CarbonImmutable $end;

    /** @var bool Se true, non effettua scritture (solo log) */
    protected bool $dryRun;

    /** @var string Trace ID per correlazione log */
    protected string $traceId;

    /**
     * @param  CarbonImmutable  $windowStart  Inizio finestra (inclusa)
     * @param  CarbonImmutable  $windowEnd    Fine finestra (inclusa)
     * @param  bool             $dryRun       Se true, nessuna scrittura
     */
    public function __construct(CarbonImmutable $windowStart, CarbonImmutable $windowEnd, bool $dryRun = false)
    {
        $this->start   = $windowStart;
        $this->end     = $windowEnd;
        $this->dryRun  = $dryRun;
        $this->traceId = (string) Str::uuid();
        // $this->onQueue('supply'); // opzionale: coda dedicata
    }

    /**
     * Entry point del Job.
     * @return void
     */
    public function handle(): void
    {
        $logger = Log::channel(config('supply_reconcile.log_channel', 'stack'));

        // Log di avvio run
        $logger->info('[Supply:RUN-START]', [
            'trace'   => $this->traceId,
            'window'  => $this->start->toDateString() . ' → ' . $this->end->toDateString(),
            'dry_run' => $this->dryRun,
            'env'     => app()->environment(),
        ]);

        // Crea record telemetria iniziale con contatori a 0
        /** @var SupplyRun $run */
        $run = SupplyRun::create([
            'window_start' => $this->start->toDateString(),
            'window_end'   => $this->end->toDateString(),
            'week_label'   => $this->start->format('o-\WW'),
            'started_at'   => now(),
            'trace_id'     => $this->traceId,
            'result'       => 'ok',
            'orders_scanned'               => 0,
            'orders_skipped_fully_covered' => 0,
            'orders_touched'               => 0,
            'stock_reservation_lines'      => 0,
            'stock_reserved_qty'           => 0,
            'po_reservation_lines'         => 0,
            'po_reserved_qty'              => 0,
            'components_in_shortfall'      => 0,
            'shortfall_total_qty'          => 0,
            'purchase_orders_created'      => 0,
            'created_po_ids'               => [],
        ]);

        // Accumulatori shortfall e tracking ordini toccati
        /** @var Collection<int,float> $shortfallByComponent */
        $shortfallByComponent    = collect(); // [component_id => qty]
        /** @var array<int, array<int, array{order_id:int, qty:float, delivery_date:string}>> $shortfallDetailPerComp */
        $shortfallDetailPerComp  = [];        // component_id => [ {order_id, qty, delivery_date}, ... ]
        /** @var array<int,bool> $touchedOrderIds */
        $touchedOrderIds         = [];        // set/dizionario: chiave = order_id toccato
        /** @var array<int, array{order_id:int, delivery_date:string, leftover:array<int,float>}> $ordersPendingAllocation */
        $ordersPendingAllocation = [];        // per riallocazione post-PO cumulativi

        try {
            /**
             * 1) Recupera gli ordini candidati:
             *    - solo confermati (status=1)
             *    - delivery_date in [start, end]
             */
            $orders = Order::query()
                ->where('status', 1)
                ->whereDate('delivery_date', '>=', $this->start->toDateString())
                ->whereDate('delivery_date', '<=', $this->end->toDateString())
                ->with([
                    'items:id,order_id,product_id,quantity',
                    'items.variable:id,order_item_id,fabric_id,color_id,resolved_component_id',
                ])
                ->orderBy('delivery_date')
                ->get();

            $run->increment('orders_scanned', $orders->count());
            $logger->info('[Supply:ORDERS-CANDIDATES]', [
                'trace' => $this->traceId,
                'count' => $orders->count(),
            ]);

            /**
             * 2) Loop ordini: calcolo fabbisogno, prenoto da stock/PO, accumulo shortfall.
             */
            foreach ($orders as $order) {
                $this->logInfo($logger, 'ORDER:START', [
                    'order_id'      => $order->id,
                    'delivery_date' => (string) $order->delivery_date,
                    'items'         => $order->items->count(),
                ]);

                // Prepara righe per explodeBom (gestisce variabili nel service)
                $lines = [];
                foreach ($order->items as $it) {
                    $lines[] = [
                        'product_id' => (int) $it->product_id,
                        'quantity'   => (float) $it->quantity,
                        'fabric_id'  => $it->variable?->fabric_id,
                        'color_id'   => $it->variable?->color_id,
                    ];
                }
                if (empty($lines)) {
                    $this->logDebug($logger, 'ORDER:SKIP-NO-LINES', ['order_id' => $order->id]);
                    continue;
                }

                // Calcolo fabbisogno (BOM + variabili)
                $inv        = InventoryService::forDelivery($order->delivery_date, $order->id);
                $components = $inv->explodeBom($lines); // Collection<component_id => qty>

                $this->logDebug($logger, 'ORDER:NEED', [
                    'order_id'        => $order->id,
                    'need_components' => $components->count(),
                    'need_sum'        => round($components->sum(), 3),
                ]);

                // Se già coperto al 100% da prenotazioni → skip
                if ($this->isFullyCoveredByReservations($order->id, $components)) {
                    $run->increment('orders_skipped_fully_covered');
                    $this->logInfo($logger, 'ORDER:SKIP-FULLY-COVERED', ['order_id' => $order->id]);
                    continue;
                }

                // Foto disponibilità complessiva
                /** @var AvailabilityResult $check */
                $check = $inv->check($lines);
                $this->logDebug($logger, 'ORDER:AVAIL-CHECKED', [
                    'order_id'   => $order->id,
                    'has_result' => (bool) $check,
                ]);

                // Flag per segnare se questo OC è stato "toccato" durante il loop
                $orderTouchedThisRun = false;

                /**
                 * 2.1) Prenotazione da STOCK (FIFO)
                 */
                $fromStock = $this->reserveFromStockFifo($order->id, $components, $this->dryRun);
                if ($fromStock['lines'] > 0) {
                    $run->increment('stock_reservation_lines', $fromStock['lines']);
                    $run->stock_reserved_qty = (float) $run->stock_reserved_qty + $fromStock['qty'];
                    $run->save();

                    $orderTouchedThisRun = true;

                    $this->logInfo($logger, 'ORDER:RESERVED-STOCK', [
                        'order_id' => $order->id,
                        'lines'    => $fromStock['lines'],
                        'qty'      => round($fromStock['qty'], 3),
                    ]);
                } else {
                    $this->logDebug($logger, 'ORDER:RESERVED-STOCK-NONE', ['order_id' => $order->id]);
                }

                /**
                 * 2.2) Residuo dopo lo stock
                 */
                $residualAfterStock = $this->computeResidualAfterStock($order->id, $components);
                $this->logDebug($logger, 'ORDER:RESIDUAL-AFTER-STOCK', [
                    'order_id'   => $order->id,
                    'components' => count($residualAfterStock),
                    'sum'        => round(array_sum($residualAfterStock), 3),
                ]);

                /**
                 * 2.3) Prenotazione da PO esistenti (ETA ≤ delivery)
                 */
                $fromPo = $this->reserveFromIncomingPo($order->id, $residualAfterStock, $order->delivery_date, $this->dryRun);
                if ($fromPo['lines'] > 0) {
                    $run->increment('po_reservation_lines', $fromPo['lines']);
                    $run->po_reserved_qty = (float) $run->po_reserved_qty + $fromPo['qty'];
                    $run->save();

                    $orderTouchedThisRun = true;

                    $this->logInfo($logger, 'ORDER:RESERVED-PO', [
                        'order_id' => $order->id,
                        'lines'    => $fromPo['lines'],
                        'qty'      => round($fromPo['qty'], 3),
                    ]);
                } else {
                    $this->logDebug($logger, 'ORDER:RESERVED-PO-NONE', ['order_id' => $order->id]);
                }

                /**
                 * 2.4) Residuo finale: accumula shortfall + prepara riallocazione
                 */
                $leftover = $this->computeResidualAfterReservations($order->id, $components);
                if (!empty($leftover)) {
                    $sumLeft = 0.0;
                    foreach ($leftover as $cid => $qty) {
                        if ($qty <= 1e-6) continue;
                        $sumLeft += $qty;
                        $shortfallByComponent[$cid] = ($shortfallByComponent[$cid] ?? 0.0) + $qty;
                        $shortfallDetailPerComp[$cid][] = [
                            'order_id'      => $order->id,
                            'qty'           => round($qty, 3),
                            'delivery_date' => (string) $order->delivery_date,
                        ];
                    }

                    // Questo OC verrà coperto dai PO cumulativi → consideralo "toccato"
                    $orderTouchedThisRun = true;

                    $ordersPendingAllocation[] = [
                        'order_id'      => $order->id,
                        'delivery_date' => (string) $order->delivery_date,
                        'leftover'      => $leftover,
                    ];

                    $this->logInfo($logger, 'ORDER:SHORTFALL', [
                        'order_id'   => $order->id,
                        'components' => count($leftover),
                        'sum'        => round($sumLeft, 3),
                        'note'       => 'Useremo PO cumulativi e poi riallocheremo su questo OC.',
                    ]);
                } else {
                    $this->logInfo($logger, 'ORDER:COVERED-AFTER-RESERVATIONS', [
                        'order_id' => $order->id,
                    ]);
                }

                // Se l’OC è stato toccato in una qualsiasi fase → segna nel set
                if ($orderTouchedThisRun) {
                    $touchedOrderIds[$order->id] = true;
                }
            }

            /**
             * 3) Telemetria shortfall aggregato (prima della creazione PO)
             */
            $run->components_in_shortfall = count($shortfallByComponent);
            $run->shortfall_total_qty     = round(array_sum($shortfallByComponent->all()), 3);
            $run->save();

            /* ───────────────────── 4) Shortfall → PO cumulativi ───────────────────── */

            $createdPoIds     = [];
            $createdPoNumbers = [];

            if (!$this->dryRun && $run->components_in_shortfall > 0) {
                // 4.1) Collezione standard {component_id, shortage}
                $stillShort = collect($shortfallByComponent->all())
                    ->filter(fn ($q) => $q > 1e-9)
                    ->map(fn ($q, $cid) => ['component_id' => (int) $cid, 'shortage' => (float) $q])
                    ->values();

                $this->logInfo($logger, 'RUN:SHORTFALL-AGG', [
                    'rows' => $stillShort->count(),
                    'sum'  => round((float) $stillShort->sum('shortage'), 3),
                ]);

                // 4.2) Enrichment supplier/lead_time/unit_price
                $shortCol = ProcurementService::buildShortageCollection($stillShort);

                $this->logDebug($logger, 'RUN:SHORTFALL-ENRICHED', [
                    'rows' => $shortCol->count(),
                    'head' => $shortCol->take(5)->values(),
                ]);

                // 4.3) Crea PO cumulativi (originOcId=0 ⇒ cumulativo, senza PoReservation interna)
                $procResult = ProcurementService::fromShortage($shortCol, 0);

                // 4.4) Estrai ID e numeri PO creati
                $createdPoIds     = collect($procResult['pos'] ?? [])->pluck('id')->all();
                $createdPoNumbers = collect($procResult['po_numbers'] ?? [])->all();

                $run->purchase_orders_created = is_countable($createdPoIds) ? count($createdPoIds) : 0;
                $run->created_po_ids          = $createdPoIds;
                $run->save();

                $this->logInfo($logger, 'RUN:PO-CUMULATIVE-CREATED', [
                    'po_ids'     => $createdPoIds,
                    'po_numbers' => $createdPoNumbers,
                ]);

                // 4.5) Rialloca dai nuovi PO verso OGNI ordine rimasto scoperto (crea po_reservations per-OC)
                $poLinesTot = 0; $poQtyTot = 0.0;
                foreach ($ordersPendingAllocation as $row) {
                    $orderId      = (int) $row['order_id'];
                    $deliveryDate = $row['delivery_date'];
                    $leftover     = $row['leftover'];

                    $fromPo = $this->reserveFromIncomingPo($orderId, $leftover, $deliveryDate, /* dry */ false);

                    $poLinesTot += (int) $fromPo['lines'];
                    $poQtyTot   += (float) $fromPo['qty'];

                    // Se la riallocazione ha creato prenotazioni, assicurati che l’OC risulti toccato
                    if ($fromPo['lines'] > 0) {
                        $touchedOrderIds[$orderId] = true;
                    }
                }

                if ($poLinesTot > 0) {
                    $run->increment('po_reservation_lines', $poLinesTot);
                    $run->po_reserved_qty = (float) $run->po_reserved_qty + $poQtyTot;
                    $run->save();
                }

                $this->logInfo($logger, 'RUN:PO-REALLOCATION', [
                    'orders'         => count($ordersPendingAllocation),
                    'po_lines_added' => $poLinesTot,
                    'po_qty_added'   => round($poQtyTot, 3),
                ]);

                // 4.6) Consolida generated_by_order_customer_id sulle righe PO mono-OC
                $cons = $this->consolidateGeneratedByForPoItems($createdPoIds);
                $this->logInfo($logger, 'RUN:PO-GENERATED_BY-CONSOLIDATE', [
                    'po_ids'    => $createdPoIds,
                    'updated'   => $cons['updated'],
                    'kept_null' => $cons['kept_null'],
                ]);
            } else {
                $this->logInfo($logger, 'RUN:PO-CUMULATIVE-SKIPPED', [
                    'reason' => $this->dryRun ? 'dry_run abilitato' : 'nessuno shortfall aggregato',
                ]);
            }

            /**
             * 5) Persistenza e log di orders_touched (set → numero univoco di OC toccati)
             */
            $run->orders_touched = count($touchedOrderIds);
            $run->save();

            $this->logDebug($logger, 'RUN:TOUCHED-ORDERS', [
                'order_ids' => array_keys($touchedOrderIds),
            ]);

            /**
             * 6) Riepilogo run
             */
            $this->logInfo($logger, 'RUN:SUMMARY', [
                'orders_scanned'   => (int) $run->orders_scanned,
                'orders_skipped'   => (int) $run->orders_skipped_fully_covered,
                'orders_touched'   => (int) $run->orders_touched, // ← ora è persistito
                'stock_lines'      => (int) ($run->stock_reservation_lines ?? 0),
                'stock_qty'        => round((float) ($run->stock_reserved_qty ?? 0), 3),
                'po_lines'         => (int) ($run->po_reservation_lines ?? 0),
                'po_qty'           => round((float) ($run->po_reserved_qty ?? 0), 3),
                'short_components' => (int) ($run->components_in_shortfall ?? 0),
                'short_qty'        => round((float) ($run->shortfall_total_qty ?? 0), 3),
                'po_created'       => (int) ($run->purchase_orders_created ?? 0),
                'po_ids'           => $run->created_po_ids ?? [],
            ]);

            // (facoltativo) Dettaglio shortfall per componente
            if ($run->components_in_shortfall > 0) {
                $this->logDebug($logger, 'RUN:SHORTFALL-DETAIL', [
                    'by_component' => collect($shortfallDetailPerComp)->map(function ($rows, $cid) {
                        return [
                            'component_id'  => (int) $cid,
                            'total_missing' => round(array_sum(array_column($rows, 'qty')), 3),
                            'orders'        => $rows,
                        ];
                    })->values()->all(),
                ]);
            }

        } catch (\Throwable $e) {
            // Marca errore e logga
            $run->result = 'error';
            $run->error_context = [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];
            $run->save();

            $this->logError($logger, 'RUN:ERROR', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

        } finally {
            // Chiusura run con durata sempre non-negativa
            $finishedAt = now();
            $startedAt  = $run->started_at instanceof \Carbon\CarbonInterface
                ? $run->started_at
                : \Illuminate\Support\Carbon::parse($run->started_at);

            $run->finished_at = $finishedAt;
            $run->duration_ms = max(0, (int) $startedAt->diffInMilliseconds($finishedAt, true));
            $run->save();

            // Retention: conserva solo gli ultimi N run
            $deleted = $this->applyRetention((int) config('supply_reconcile.retention_max_runs', 60));

            $logger->info('[Supply:RUN-END]', [
                'trace'             => $this->traceId,
                'duration_ms'       => $run->duration_ms,
                'result'            => $run->result,
                'retention_deleted' => $deleted,
            ]);
        }
    }

    /* =============================== HELPERS =============================== */

    /**
     * Verifica copertura completa (stock + PO) per l'OC.
     *
     * @param  int                 $orderId
     * @param  Collection<int,float> $componentsNeeded
     * @return bool
     */
    protected function isFullyCoveredByReservations(int $orderId, Collection $componentsNeeded): bool
    {
        $stock = DB::table('stock_reservations as sr')
            ->join('stock_levels as sl', 'sl.id', '=', 'sr.stock_level_id')
            ->where('sr.order_id', $orderId)
            ->selectRaw('sl.component_id, SUM(sr.quantity) as qty')
            ->groupBy('sl.component_id')
            ->pluck('qty', 'sl.component_id');

        $po = DB::table('po_reservations as pr')
            ->join('order_items as oi', 'oi.id', '=', 'pr.order_item_id')
            ->where('pr.order_customer_id', $orderId)
            ->selectRaw('oi.component_id, SUM(pr.quantity) as qty')
            ->groupBy('oi.component_id')
            ->pluck('qty', 'oi.component_id');

        foreach ($componentsNeeded as $cid => $need) {
            $have = (float) ($stock[$cid] ?? 0) + (float) ($po[$cid] ?? 0);
            if ($have + 1e-6 < (float) $need) {
                return false;
            }
        }
        return true;
    }

    /**
     * Prenota da stock (FIFO) il più possibile.
     *
     * @param  int                   $orderId
     * @param  Collection<int,float> $components
     * @param  bool                  $dry
     * @return array{lines:int, qty:float}
     */
    protected function reserveFromStockFifo(int $orderId, Collection $components, bool $dry): array
    {
        $logger = Log::channel(config('supply_reconcile.log_channel', 'stack'));
        $lines = 0; $qtyTot = 0.0;

        foreach ($components as $cid => $qtyNeed) {
            $left = (float) $qtyNeed;

            $batches = StockLevel::query()
                ->where('component_id', (int) $cid)
                ->orderBy('created_at')
                ->get();

            foreach ($batches as $sl) {
                if ($left <= 1e-9) break;

                $already = (float) $sl->reservations()->sum('quantity');
                $free    = (float) $sl->quantity - $already;
                if ($free <= 1e-9) continue;

                $take = (float) min($free, $left);

                if ($take > 1e-9) {
                    if (!$dry) {
                        DB::transaction(function () use ($sl, $orderId, $take) {
                            StockReservation::create([
                                'stock_level_id' => $sl->id,
                                'order_id'       => $orderId,
                                'quantity'       => $take,
                            ]);
                        });
                    }

                    $lines++;
                    $qtyTot += $take;
                    $left   -= $take;

                    $this->logDebug($logger, 'RESERVE:STOCK-BATCH', [
                        'order_id'     => $orderId,
                        'component_id' => (int) $cid,
                        'stock_level'  => $sl->id,
                        'batch_qty'    => (float) $sl->quantity,
                        'batch_free'   => round($free, 3),
                        'take'         => round($take, 3),
                        'remaining_for_component' => round($left, 3),
                        'dry_run'      => $dry,
                    ]);
                }
            }

            if ($left > 1e-6) {
                $this->logDebug($logger, 'RESERVE:STOCK-INSUFFICIENT', [
                    'order_id'     => $orderId,
                    'component_id' => (int) $cid,
                    'still_needed' => round($left, 3),
                ]);
            }
        }

        return ['lines' => $lines, 'qty' => $qtyTot];
    }

    /**
     * Residuo dopo prenotazioni su stock (per componente).
     *
     * @param  int                   $orderId
     * @param  Collection<int,float> $componentsNeeded
     * @return array<int,float>      [component_id => qty]
     */
    protected function computeResidualAfterStock(int $orderId, Collection $componentsNeeded): array
    {
        $stock = DB::table('stock_reservations as sr')
            ->join('stock_levels as sl', 'sl.id', '=', 'sr.stock_level_id')
            ->where('sr.order_id', $orderId)
            ->selectRaw('sl.component_id, SUM(sr.quantity) as qty')
            ->groupBy('sl.component_id')
            ->pluck('qty', 'sl.component_id');

        $residual = [];
        foreach ($componentsNeeded as $cid => $need) {
            $reserved = (float) ($stock[$cid] ?? 0);
            $left     = max(0.0, (float) $need - $reserved);
            if ($left > 1e-6) $residual[(int) $cid] = $left;
        }
        return $residual;
    }

    /**
     * Prenota il residuo su PO esistenti con ETA compatibile.
     *
     * @param  int                  $orderId
     * @param  array<int,float>     $residualAfterStock [component_id => qty]
     * @param  \DateTimeInterface|string $deliveryDate
     * @param  bool                 $dry
     * @return array{lines:int, qty:float}
     */
    protected function reserveFromIncomingPo(int $orderId, array $residualAfterStock, $deliveryDate, bool $dry): array
    {
        $logger = Log::channel(config('supply_reconcile.log_channel', 'stack'));
        $lines = 0; $qtyTot = 0.0;
        if (empty($residualAfterStock)) return ['lines' => 0, 'qty' => 0.0];

        foreach ($residualAfterStock as $cid => $qtyNeeded) {
            if ($qtyNeeded <= 1e-6) continue;
            $originalNeed = $qtyNeeded;

            // Trova righe PO aperte per questo componente con ETA tra oggi e delivery OC
            $poLines = DB::table('order_items   as oi')
                ->join  ('orders        as o',  'o.id',  '=', 'oi.order_id')
                ->join  ('order_numbers as on', 'on.id', '=', 'o.order_number_id')
                ->leftJoin('po_reservations as pr', 'pr.order_item_id', '=', 'oi.id')
                ->where  ('on.order_type', 'supplier')          // solo PO fornitore
                ->whereNull('o.bill_number')                    // PO aperti
                ->where   ('oi.component_id', (int) $cid)
                ->whereBetween('o.delivery_date', [now()->startOfDay(), CarbonImmutable::parse($deliveryDate)->endOfDay()])
                ->groupBy('oi.id', 'oi.quantity', 'oi.component_id', 'o.delivery_date')
                ->selectRaw('oi.id, oi.quantity, oi.component_id, o.delivery_date,
                             GREATEST(oi.quantity - COALESCE(SUM(pr.quantity), 0), 0) as free_qty')
                ->orderBy('o.delivery_date')
                ->get();

            foreach ($poLines as $line) {
                if ($qtyNeeded <= 1e-6) break;

                $take = (float) min($line->free_qty, $qtyNeeded);
                if ($take <= 1e-6) continue;

                if (!$dry) {
                    PoReservation::create([
                        'order_item_id'     => (int) $line->id,
                        'order_customer_id' => $orderId,
                        'quantity'          => $take,
                    ]);
                }

                $lines++;
                $qtyTot   += $take;
                $qtyNeeded -= $take;

                $this->logDebug($logger, 'RESERVE:PO-LINE', [
                    'order_id'      => $orderId,
                    'component_id'  => (int) $cid,
                    'po_item_id'    => (int) $line->id,
                    'po_eta'        => (string) $line->delivery_date,
                    'po_line_qty'   => (float) $line->quantity,
                    'po_free_before'=> round((float) $line->free_qty, 3),
                    'take'          => round($take, 3),
                    'remaining_for_component' => round($qtyNeeded, 3),
                    'dry_run'       => $dry,
                ]);
            }

            if ($qtyNeeded > 1e-6) {
                $this->logInfo($logger, 'RESERVE:PO-INSUFFICIENT', [
                    'order_id'     => $orderId,
                    'component_id' => (int) $cid,
                    'needed'       => round($originalNeed, 3),
                    'covered'      => round($originalNeed - $qtyNeeded, 3),
                    'missing'      => round($qtyNeeded, 3),
                ]);
            }
        }

        return ['lines' => $lines, 'qty' => $qtyTot];
    }

    /**
     * Residuo finale dopo tutte le prenotazioni (stock + PO).
     *
     * @param  int                   $orderId
     * @param  Collection<int,float> $componentsNeeded
     * @return array<int,float>      [component_id => qty]
     */
    protected function computeResidualAfterReservations(int $orderId, Collection $componentsNeeded): array
    {
        $stock = DB::table('stock_reservations as sr')
            ->join('stock_levels as sl', 'sl.id', '=', 'sr.stock_level_id')
            ->where('sr.order_id', $orderId)
            ->selectRaw('sl.component_id, SUM(sr.quantity) as qty')
            ->groupBy('sl.component_id')
            ->pluck('qty', 'sl.component_id');

        $po = DB::table('po_reservations as pr')
            ->join('order_items as oi', 'oi.id', '=', 'pr.order_item_id')
            ->where('pr.order_customer_id', $orderId)
            ->selectRaw('oi.component_id, SUM(pr.quantity) as qty')
            ->groupBy('oi.component_id')
            ->pluck('qty', 'oi.component_id');

        $leftover = [];
        foreach ($componentsNeeded as $cid => $need) {
            $have = (float) ($stock[$cid] ?? 0) + (float) ($po[$cid] ?? 0);
            $left = max(0.0, (float) $need - $have);
            if ($left > 1e-6) $leftover[(int) $cid] = $left;
        }
        return $leftover;
    }

    /**
     * Consolida generated_by_order_customer_id sulle righe PO:
     * - se tutte le po_reservations della riga appartengono allo stesso OC ⇒ setta quell'OC;
     * - altrimenti lascia NULL (riga cumulativa multi-OC).
     *
     * @param  array<int>  $poIds
     * @return array{updated:int, kept_null:int}
     */
    protected function consolidateGeneratedByForPoItems(array $poIds): array
    {
        if (empty($poIds)) return ['updated' => 0, 'kept_null' => 0];

        $poItemIds = DB::table('order_items')
            ->whereIn('order_id', $poIds)
            ->pluck('id')
            ->all();

        if (empty($poItemIds)) return ['updated' => 0, 'kept_null' => 0];

        $updated = 0; $keptNull = 0;

        $groups = DB::table('po_reservations')
            ->selectRaw('order_item_id, COUNT(DISTINCT order_customer_id) as oc_count')
            ->whereIn('order_item_id', $poItemIds)
            ->groupBy('order_item_id')
            ->pluck('oc_count', 'order_item_id');

        foreach ($poItemIds as $itemId) {
            $ocCount = (int) ($groups[$itemId] ?? 0);

            if ($ocCount === 1) {
                $ocId = (int) DB::table('po_reservations')
                    ->where('order_item_id', $itemId)
                    ->value('order_customer_id');

                DB::table('order_items')
                    ->where('id', $itemId)
                    ->update(['generated_by_order_customer_id' => $ocId]);

                $updated++;
            } else {
                DB::table('order_items')
                    ->where('id', $itemId)
                    ->update(['generated_by_order_customer_id' => null]);

                $keptNull++;
            }
        }

        return ['updated' => $updated, 'kept_null' => $keptNull];
    }

    /**
     * Retention: mantiene solo gli ultimi N run.
     *
     * @param  int $maxRuns
     * @return int Numero di run cancellati
     */
    protected function applyRetention(int $maxRuns): int
    {
        if ($maxRuns <= 0) {
            return 0;
        }

        $total = SupplyRun::count();
        if ($total <= $maxRuns) {
            return 0; // nulla da potare
        }

        $toDelete = $total - $maxRuns;
        $deleted  = 0;
        $chunk    = 200; // dimensione chunk: elimina al massimo 200 per batch

        // Seleziona gli ID più vecchi, ordinando prima per started_at, poi per id
        while ($deleted < $toDelete) {
            $take = min($chunk, $toDelete - $deleted);

            $ids = SupplyRun::query()
                ->orderByRaw('CASE WHEN started_at IS NULL THEN 1 ELSE 0 END ASC') // preferisci started_at non-null
                ->orderBy('started_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($take)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break; // sicurezza
            }

            SupplyRun::query()
                ->whereIn('id', $ids)
                ->delete();

            $deleted += count($ids);
        }

        return $deleted;
    }

    /* --------------------------- LOG helpers --------------------------- */

    /**
     * Log INFO con prefisso e trace_id.
     * @param  \Psr\Log\LoggerInterface  $logger
     * @param  string                    $tag
     * @param  array<string,mixed>       $context
     * @return void
     */
    protected function logInfo($logger, string $tag, array $context = []): void
    {
        $logger->info("[Supply:$tag]", array_merge(['trace' => $this->traceId], $context));
    }

    /**
     * Log DEBUG con prefisso e trace_id.
     * @param  \Psr\Log\LoggerInterface  $logger
     * @param  string                    $tag
     * @param  array<string,mixed>       $context
     * @return void
     */
    protected function logDebug($logger, string $tag, array $context = []): void
    {
        $logger->debug("[Supply:$tag]", array_merge(['trace' => $this->traceId], $context));
    }

    /**
     * Log ERROR con prefisso e trace_id.
     * @param  \Psr\Log\LoggerInterface  $logger
     * @param  string                    $tag
     * @param  array<string,mixed>       $context
     * @return void
     */
    protected function logError($logger, string $tag, array $context = []): void
    {
        $logger->error("[Supply:$tag]", array_merge(['trace' => $this->traceId], $context));
    }
}
