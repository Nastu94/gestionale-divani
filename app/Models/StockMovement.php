<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modello per la tabella 'stock_movements'.
 *
 * Registra lo storico di tutte le entrate e uscite di magazzino.
 */
class StockMovement extends Model
{
    use LogsActivity;

    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'stock_level_id', // ID della giacenza di riferimento
        'type',           // Tipo movimento (in, out, reserve)
        'quantity',       // Quantità movimentata
        'note',           // Note aggiuntive
        'moved_at',       // Data del movimento
    ];

    protected static $logName = 'stock_movement';

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
            ->useLogName('stock_movement'); // nome del log per distinguere
    }
    
    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'stock_level_id',
        'type',        // Tipo movimento (in, out, reserve)
        'quantity',    // Quantità movimentata
        'note',        // Note aggiuntive
        'moved_at',    // Data del movimento
    ];

    /**
     * Giacenza di riferimento per questo movimento.
     */
    public function stockLevel()
    {
        return $this->belongsTo(StockLevel::class);
    }
}