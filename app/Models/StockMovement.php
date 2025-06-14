<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
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
        'type',           // Tipo movimento (in, out, transfer_in, ...)
        'quantity',       // Quantità movimentata
        'reference_type', // Tipo di riferimento (es. order, production)
        'reference_id',   // ID del riferimento (es. ID ordine)
        'note',           // Note aggiuntive
        'moved_at',       // Data del movimento
    ];
    protected static $logName = 'stock_movement';
    
    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'stock_level_id',
        'type',        // Tipo movimento (in, out, transfer_in, ...)
        'quantity',    // Quantità movimentata
        'reference_type', // Tipo di riferimento
        'reference_id',   // ID del riferimento
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