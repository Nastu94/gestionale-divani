<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use App\Jobs\ReconcileSupplyForWindowJob;

/**
 * Comando artisan per lanciare la riconciliazione settimanale.
 *
 * NOTE:
 * - Non rinomina/rompe nulla dell'esistente.
 * - Parametri opzionali permettono override della finestra per test manuali.
 */
class WeeklySupplyReconcile extends Command
{
    /** @var string Signature artisan del comando */
    protected $signature = 'reservations:weekly-reconcile
        {--start= : Data di inizio finestra (YYYY-MM-DD). Default: oggi}
        {--days= : Ampiezza finestra in giorni futuri. Default: config(supply_reconcile.window_days)}
        {--dry : Forza dry-run (ignora config) per test senza scritture}';

    /** @var string Descrizione visibile in `php artisan list` */
    protected $description = 'Riconcilia automaticamente coperture (stock/PO) ordini confermati nella finestra configurata.';

    /**
     * Esegue il comando: calcola parametri e dispatcha il Job.
     */
    public function handle(): int
    {
        // Risolvi parametri con fallback alla config
        $tz        = config('supply_reconcile.schedule_timezone', 'Europe/Rome');
        $start     = $this->option('start')
                       ? Carbon::parse($this->option('start'), $tz)->startOfDay()
                       : Carbon::now($tz)->startOfDay();

        $days      = (int) ($this->option('days') ?? config('supply_reconcile.window_days', 30));
        $end       = (clone $start)->addDays($days); // inclusivo a livello logico (gestito nel Job)

        $dryRun    = $this->option('dry') ? true : (bool) config('supply_reconcile.dry_run', false);

        // Log canale dedicato se configurato
        $logger = Log::channel(config('supply_reconcile.log_channel', 'stack'));

        $logger->info('[WeeklySupplyReconcile] Dispatch job', [
            'start'   => $start->toDateString(),
            'end'     => $end->toDateString(),
            'days'    => $days,
            'dry_run' => $dryRun,
        ]);

        // Dispatch sincrono o async? Usiamo la coda (raccomandato).
        Bus::dispatch(new ReconcileSupplyForWindowJob(
            windowStart: $start->toImmutable(),
            windowEnd:   $end->toImmutable(),
            dryRun:      $dryRun
        ));

        $this->info("Job lanciato per finestra {$start->toDateString()} → {$end->toDateString()} (dry_run=" . ($dryRun ? 'true' : 'false') . ").");
        return self::SUCCESS;
    }
}
