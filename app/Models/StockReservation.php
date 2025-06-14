<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\StockLevel;
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modello per la tabella 'stock_reservations'.
 *
 * Impegni di stock legati ad ordini cliente.
 */
class StockReservation extends Model
{
    use LogsActivity;

    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'stock_level_id', // ID della giacenza
        'order_id',       // ID dell'ordine associato
        'quantity',       // Quantità riservata
    ];
    protected static $logName = 'stock_reservation';
    
    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'stock_level_id',
        'order_id',
        'quantity', // Quantità riservata
    ];

    /**
     * Giacenza associata a questo impegno.
     */
    public function stockLevel()
    {
        return $this->belongsTo(StockLevel::class);
    }

    /**
     * Ordine associato a questo impegno.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}