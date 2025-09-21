<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Model: ProductStockLevel
 *
 * Rappresenta lo stock minimo dei prodotti finiti (provenienti da resi in buone condizioni, rivendibili).
 * È intenzionalmente "snello", come richiesto: nessuna reservations/movements, solo stato puntuale.
 *
 * Campi:
 * - order_id: collega all'ordine origine (per risalire a prodotto/variabili dalle righe ordine).
 * - warehouse_id: magazzino dei prodotti finiti (tipicamente quello "prodotti").
 * - quantity: quantità a stock (es. 1.00).
 * - reserved_for: testo descrittivo libero (es. "OC#123").
 */
class ProductStockLevel extends Model
{   
    use LogsActivity;
    protected static $logName = 'product_stock_level';
    protected static $logAttributes = [
        'order_id',
        'warehouse_id',
        'quantity',
        'reserved_for',
        'fabric_id',
        'color_id',
        'product_id',
    ];
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
            ->logOnlyDirty();
    } 

    /** @var array<int, string> */
    protected $fillable = [
        'order_id',
        'warehouse_id',
        'quantity',
        'reserved_for',
        'fabric_id',
        'color_id',
        'product_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    /**
     * Ordine di riferimento (reso da cui proviene il prodotto finito rivendibile).
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Magazzino in cui è presente il prodotto finito.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** 
     * Ordine cliente per il quale questa giacenza è prenotata (reserved_for). 
     */
    public function reservedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'reserved_for');
    }

    /**
     * Variabile: tessuto associato alla giacenza (se presente).
     */
    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    /** 
     * Variabile: colore associato alla giacenza (se presente). 
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }
}
