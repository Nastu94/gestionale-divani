<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modello per la tabella 'stock_movements'.
 *
 * Registra lo storico di tutte le entrate e uscite di magazzino.
 */
class StockMovement extends Model
{
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