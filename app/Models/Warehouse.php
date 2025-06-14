<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modello per la tabella 'warehouses'.
 *
 * Rappresenta i depositi fisici o virtuali di magazzino.
 */
class Warehouse extends Model
{
    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',     // Nome deposito
        'type',     // Tipo deposito
    ];

    /**
     * Giacenze per componente in questo deposito.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Movimentazioni di magazzino legate a questo deposito.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Impegni di stock in questo deposito.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockReservations()
    {
        return $this->hasMany(StockReservation::class);
    }
}