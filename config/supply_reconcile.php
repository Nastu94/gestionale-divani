<?php

/**
* Configurazione riconciliazione settimanale coperture (stock/PO) per ordini confermati.
*
* Nota: tutte le opzioni sono sovrascrivibili via ENV senza toccare il codice.
*/
return [
    /*
    |--------------------------------------------------------------------------
    | Attivazione feature
    |--------------------------------------------------------------------------
    | true = lo scheduler/command può eseguire il job settimanale.
    */
    'enabled' => (bool) env('SUPPLY_RECONCILE_ENABLED', true),


    /*
    |--------------------------------------------------------------------------
    | Finestra temporale
    |--------------------------------------------------------------------------
    | Giorni futuri da considerare rispetto ad "oggi" (inclusivo).
    | Requisito: 30 giorni.
    */
    'window_days' => (int) env('SUPPLY_RECONCILE_WINDOW_DAYS', 30),


    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    | Orario locale (Europe/Rome) in cui far partire il run del lunedì.
    | Esempio formato HH:MM (24h): '06:15'.
    */
    'schedule_time' => env('SUPPLY_RECONCILE_SCHEDULE_TIME', '06:00'),
    'schedule_timezone' => env('SUPPLY_RECONCILE_TZ', 'Europe/Rome'),


    /*
    |--------------------------------------------------------------------------
    | Batch & performance
    |--------------------------------------------------------------------------
    | Dimensione chunk per processare le righe ordine, per controllare lock/memoria.
    */
    'batch_size' => (int) env('SUPPLY_RECONCILE_BATCH_SIZE', 250),


    /*
    |--------------------------------------------------------------------------
    | Retention per numero di run
    |--------------------------------------------------------------------------
    | Mantieni solo gli ultimi N run (richiesto ≈ 60 per coprire ~1 anno).
    */
    'retention_max_runs' => (int) env('SUPPLY_RECONCILE_RETENTION_MAX_RUNS', 60),


    /*
    |--------------------------------------------------------------------------
    | Modalità dry-run
    |--------------------------------------------------------------------------
    | Quando true, il job non effettua scritture (prenotazioni/PO), ma calcola e logga.
    | Utile per test in ambienti di staging.
    */
    'dry_run' => (bool) env('SUPPLY_RECONCILE_DRY_RUN', false),


    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    | Canale log da utilizzare per i messaggi del job/command.
    */
    'log_channel' => env('SUPPLY_RECONCILE_LOG_CHANNEL', 'supply'),


    /*
    |--------------------------------------------------------------------------
    | Sicurezza & idempotenza
    |--------------------------------------------------------------------------
    | TTL del lock in cache per evitare run concorrenti.
    */
    'lock_ttl_seconds' => (int) env('SUPPLY_RECONCILE_LOCK_TTL', 1800), // 30 minuti
];