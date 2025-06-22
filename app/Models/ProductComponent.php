<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Modello pivot per la relazione product_components.
 */
class ProductComponent extends Pivot
{   
    use LogsActivity; 
    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'product_id',   // ID del prodotto
        'component_id', // ID del componente
        'quantity',     // Quantità del componente nel prodotto
    ];
    protected static $logName = 'product_component';
    
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
            ->useLogName('product_component'); // nome del log per distinguere
    }

    /**
     * Nome della tabella pivot.
     *
     * @var string
     */
    protected $table = 'product_components';

    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'product_id',
        'component_id',
        'quantity',
    ];
}