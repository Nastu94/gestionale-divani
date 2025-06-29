<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Supplier;
use App\Models\Component;

/**
 * Modello pivot per la relazione component_supplier.
 */
class ComponentSupplier extends Model
{
    use LogsActivity;

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
        'component_id',      
        'supplier_id',       
        'lead_time_days',    
        'last_cost',         
    ];
    
    /** Cast automatici */
    protected $casts = [
        'last_cost'      => 'decimal:4',
        'lead_time_days' => 'integer',
    ];

    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'component_id',      
        'supplier_id',       
        'lead_time_days',    
        'last_cost',         
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
            ->useLogName('component_supplier'); 
    }

    /**
     * Relazione con il modello Supplier.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relazione con il modello Component.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function component()
    {
        return $this->belongsTo(Component::class);
    }
}