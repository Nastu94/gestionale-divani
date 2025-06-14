<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modello per la tabella 'stock_levels'.
 *
 * Snapshot in tempo reale delle giacenze effettive per ogni componente e deposito.
 */
class StockLevel extends Model
{
    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */    
    protected $fillable = [
        'component_id',
        'warehouse_id',
        'internal_lot_code',   // Codice lotto interno
        'supplier_lot_code',   // Codice lotto fornitore
        'quantity',            // Quantità disponibile
    ];

    /**
     * Componente associato a questa giacenza.
     */
    public function component()
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Deposito associato a questa giacenza.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Movimentazioni legate a questa giacenza.
     */
    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Impegni di stock legati a questa giacenza.
     */
    public function reservations()
    {
        return $this->hasMany(StockReservation::class);
    }
}