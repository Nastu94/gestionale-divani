<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\GeneratesLot;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\StockLevel;
use App\Models\LotNumber;
use App\Models\Order;

/**
 * @property string  $internal_lot_code
 * @property float   $quantity
 */
class StockLevelLot extends Model
{
    use GeneratesLot;
    use LogsActivity;

    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'stock_level_id',
        'lot_number_id',
        'internal_lot_code',
        'supplier_lot_code',
        'quantity',
        'received_quantity',
    ];
    protected static $logName = 'stock_level_lot';

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
            ->useLogName('stock_level_lot'); // nome del log per distinguere
    }

    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'stock_level_id',
        'lot_number_id',
        'internal_lot_code',
        'supplier_lot_code',
        'quantity',
        'received_quantity',
    ];

    /* relazione inversa -------------------------------------------------- */
    public function stockLevel()
    {
        return $this->belongsTo(StockLevel::class);
    }

    /* relazione con il lotto fornitore ------------------------------------ */
    public function lotNumber()
    {
        return $this->belongsTo(LotNumber::class);
    }

    /* relazione con le prenotazioni cliente (via pivot order → items) ---- */
    public function reservations()
    {
        return $this->hasMany(StockReservation::class);
    }

    /* relazione con l'ordine fornitore (passando per la tabella pivot order_stock_level) ------------------------------------ */
    public function orders()
    {
        return $this->belongsToMany(
            Order::class,
            'order_stock_level',
            'stock_level_lot_id',
            'order_id'
        );
    }
}
