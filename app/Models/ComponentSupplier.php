<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Modello pivot per la relazione component_supplier.
 */
class ComponentSupplier extends Pivot
{
    use LogsActivity;

    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'component_id',      // ID del componente
        'supplier_id',       // ID del fornitore
        'lead_time_days',    // Giorni di consegna
        'last_cost',         // Costo unitario ultimo acquisto
    ];

    protected static $logName = 'component_supplier';

    /**
     * Configura le opzioni di logging per questo modello.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        // Logga tutti gli attributi 'fillable', registra solo i cambiamenti
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('component_supplier'); // nome del log per distinguere
    }

    /**
     * Nome della tabella pivot.
     *
     * @var string
     */
    protected $table = 'component_supplier';

    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'component_id',      // FK → components
        'supplier_id',       // FK → suppliers
        'lead_time_days',    // Giorni di consegna
        'last_cost',         // Costo unitario ultimo acquisto
    ];
}