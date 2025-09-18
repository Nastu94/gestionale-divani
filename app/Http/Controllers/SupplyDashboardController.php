<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ReconcileSupplyForWindowJob;
use App\Models\Order;
use App\Models\SupplyRun;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * SupplyDashboardController
 * -----------------------------------------------------------------------------
 * Mostra la dashboard dei run di riconciliazione (supply) e permette di forzare
 * l'esecuzione immediata del Job settimanale, nel rispetto dei guard-rail:
 *  - accesso riservato (permesso: orders.supplier.menage_supply)
 *  - blocco avvio se esiste un run "aperto"
 *
 * PHP 8.4 / Laravel 12 – Solo letture + dispatch del Job (ShouldQueue).
 */
class SupplyDashboardController extends Controller
{
    /**
     * GET /orders/supplier/supply
     * -----------------------------------------------------------------------------
     * - Prossimo run pianificato (LUN, orario da config)
     * - Config effettive (read-only)
     * - KPI ultimi run (ultimi 8)
     * - Tabella run con paginate
     */
    public function index(Request $request)
    {
        // ⚙️ Config effettive (read-only)
        $cfg = [
            'window_days'       => (int) config('supply_reconcile.window_days', 30),
            'schedule_time'     => (string) config('supply_reconcile.schedule_time', '06:00'),
            'schedule_timezone' => (string) config('supply_reconcile.schedule_timezone', config('app.timezone', 'UTC')),
            'retention_max'     => (int) config('supply_reconcile.retention_max_runs', 60),
            'dry_run'           => (bool) config('supply_reconcile.dry_run', false),
        ];

        // 🕒 Calcolo “prossimo Lunedì alle HH:MM” nella TZ configurata
        $now   = CarbonImmutable::now($cfg['schedule_timezone']);
        [$h, $m] = array_pad(explode(':', $cfg['schedule_time']), 2, '00');
        $todayAt = $now->setTime((int) $h, (int) $m, 0);

        $nextRun = ($now->dayOfWeekIso === 1 && $now->lessThan($todayAt))
            ? $todayAt
            : $now->next('monday')->setTime((int) $h, (int) $m, 0);

        // 🚦 C'è un run aperto?
        $hasOpenRun = SupplyRun::whereNull('finished_at')->exists();

        // 📊 KPI ultimi 8 run
        $recentN = 8;
        $recent  = SupplyRun::orderByDesc('started_at')->limit($recentN)->get();
        $kpi = [
            'runs_total'         => $recent->count(),
            'runs_ok'            => $recent->where('result', 'ok')->count(),
            'runs_error'         => $recent->where('result', 'error')->count(),
            'orders_touched_sum' => (int) $recent->sum('orders_touched'),
            'orders_touched_avg' => $recent->count() ? round($recent->avg('orders_touched'), 1) : 0,
            'covered_qty'        => (float) $recent->reduce(
                fn ($c, $r) => $c + (float)($r->stock_reserved_qty ?? 0) + (float)($r->po_reserved_qty ?? 0),
                0.0
            ),
            'po_created'         => (int) $recent->sum('purchase_orders_created'),
        ];

        // 🧾 Tabella run
        $runs = SupplyRun::query()
            ->orderByDesc('started_at')
            ->paginate(25)
            ->withQueryString();

        // 🔢 Ultimo run: numeri PO creati (se disponibili)
        $lastRun       = SupplyRun::orderByDesc('started_at')->first();
        $lastRunPoIds  = $lastRun?->created_po_ids ?? [];
        $lastRunPoNums = collect();
        if (!empty($lastRunPoIds)) {
            $pos = Order::with('orderNumber')->whereIn('id', $lastRunPoIds)->get();
            $lastRunPoNums = $pos->map(fn (Order $po) => [
                'id'     => $po->id,
                'number' => $po->orderNumber?->number,
            ]);
        }

        return view('pages.orders.supply.dashboard', [
            'cfg'           => $cfg,
            'nextRun'       => $nextRun,
            'hasOpenRun'    => $hasOpenRun,
            'kpi'           => $kpi,
            'runs'          => $runs,
            'lastRunPoNums' => $lastRunPoNums,
        ]);
    }

    /**
     * POST /orders/supplier/supply/run
     * -----------------------------------------------------------------------------
     * Forza un run ad-hoc:
     *  - Valida i parametri (giorni, dry-run)
     *  - Evita sovrapposizioni se esiste un run aperto
     *  - Dispatch del Job (ShouldQueue)
     */
    public function runNow(Request $request)
    {
        $data = $request->validate([
            'days'    => ['required', 'integer', 'min:1', 'max:60'], // safety: non oltre 60gg
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $tz    = (string) config('supply_reconcile.schedule_timezone', config('app.timezone', 'UTC'));
        $start = CarbonImmutable::now($tz)->startOfDay();
        $end   = $start->addDays((int) $data['days']);
        $dry   = (bool) ($data['dry_run'] ?? false);

        // Guard-rail: non permettere un secondo run se ce n'è uno aperto
        if (SupplyRun::whereNull('finished_at')->exists()) {
            return back()->with('error', 'Esiste già un run in corso. Attendi il completamento prima di avviarne un altro.');
        }

        // Dispatch del Job (il lavoro pesante lo farà il queue worker)
        dispatch(new ReconcileSupplyForWindowJob($start, $end, $dry));

        return redirect()
            ->route('orders.supply.dashboard')
            ->with('status', "Run avviato: finestra {$start->toDateString()} → {$end->toDateString()} (dry_run=" . ($dry ? 'true' : 'false') . ").");
    }
}
