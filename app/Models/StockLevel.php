<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Component;
use App\Models\Warehouse;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\StockLevelLot;
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Modello per la tabella 'stock_levels'.
 *
 * Snapshot in tempo reale delle giacenze effettive per ogni componente e deposito.
 */
class StockLevel extends Model
{
    use LogsActivity;    
    
    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'component_id',       // ID del componente
        'warehouse_id',       // ID del deposito
        'quantity',           // Quantità disponibile
    ];
    
    protected static $logName = 'stock_level';  
    
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
            ->useLogName('stock_level'); // nome del log per distinguere
    }

    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */    
    protected $fillable = [
        'component_id',
        'warehouse_id',
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
     * Ordini legati a questa giacenza.
     *
     * Questa relazione è una Many-to-Many attraverso la tabella pivot 'order_stock_level'.
     */
    public function orders()
    {
        return $this->belongsToMany(
            Order::class,
            'order_stock_level'
        )->withTimestamps();
    }

    /**
     * Impegni di stock legati a questa giacenza.
     */
    public function reservations()
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * Lotti associati a questa giacenza.
     *
     * Questa relazione è una One-to-Many con il modello StockLevelLot.
     */
    public function lots()
    {
        return $this->hasMany(StockLevelLot::class);
    }

    /** quantità totale ricevuta = somma lotti */
    public function getTotalQuantityAttribute(): float
    {
        return (float) $this->lots()->sum('quantity');
    }
}