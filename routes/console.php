<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\ReconcileSupplyForWindowJob;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/**
 * Task settimanale: ogni LUN alle HH:MM in timezone configurata.
 * - Calcola la finestra [oggi, oggi+window_days]
 * - Legge la modalità dry_run da config
 * - Dispatch del Job in coda (ShouldQueue)
 */
Schedule::call(function () {
    // Timezone configurata
    $tz = config('supply_reconcile.schedule_timezone', config('app.timezone', 'UTC'));

    // Finestra: oggi → oggi + N giorni (default 30)
    $today = CarbonImmutable::now($tz)->startOfDay();
    $days  = (int) config('supply_reconcile.window_days', 30);
    $end   = $today->addDays($days);

    // Dry-run
    $dry = (bool) config('supply_reconcile.dry_run', false);

    // Dispatch del Job in coda
    dispatch(new ReconcileSupplyForWindowJob($today, $end, $dry));
})
    // ⬇️ DAI UN NOME PRIMA DI withoutOverlapping()
    ->name('weekly-supply-reconcile')

    // Frequenza e orario
    ->wednesdays()
    ->at(config('supply_reconcile.schedule_time', '23:50'))
    ->timezone(config('supply_reconcile.schedule_timezone', 'Europe/Rome'))

    // Lock per evitare sovrapposizioni
    ->withoutOverlapping()

    // Un solo server (richiede cache condivisa in multi-istanza)
    ->onOneServer();