<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modello per la tabella 'orders'.
 *
 * Rappresenta ordini di acquisto o produzione.
 */
class Order extends Model
{
    use LogsActivity;

    /**
     * Attributi che devono essere registrati nel log delle attività.
     */
    protected static $logAttributes = [
        'supplier_id',  // ID del fornitore associato, se ordine fornitore
        'customer_id',  // ID del cliente associato, se ordine cliente
        'cause',      // purchase/production/return/scrap
        'total',      // Valore totale
        'ordered_at', // Data ordine
    ];

    protected static $logName = 'order';
    
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
            ->useLogName('order'); // nome del log per distinguere
    }
    
    /**
     * Attributi assegnabili in massa.
     */
    protected $fillable = [
        'supplier_id',
        'customer_id',
        'cause',      // purchase/production/return/scrap
        'total',      // Valore totale
        'ordered_at', // Data ordine
    ];

    /**
     * Fornitore associato all'ordine.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Cliente associato all'ordine (se presente).
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Righe di dettaglio dell'ordine.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Impegni di stock legati a questo ordine.
     */
    public function reservations()
    {
        return $this->hasMany(StockReservation::class);
    }
}