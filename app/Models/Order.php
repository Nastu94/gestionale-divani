<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\StockReservation;
use App\Models\OrderNumber;

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
        'parent_order_id', // ID dell'ordine padre, se presente
        'has_shortfall', // Indica se ci sono shortfall associati
        'supplier_id',  // ID del fornitore associato, se ordine fornitore
        'customer_id',  // ID del cliente associato, se ordine cliente
        'occasional_customer_id', // ID del cliente occasionale, se presente
        'order_number_id',  // ID del numero d'ordine associato
        'total',      // Valore totale
        'ordered_at', // Data ordine
        'delivery_date', // Data prevista di consegna
        'registration_date', // Data registrazione magazzino
        'bill_number', // Numero bolla di consegna
        'shipping_address', // Indirizzo di spedizione
        'min_phase', // Fase minima di produzione
        'status',       // Stato dell'ordine (completato o meno)
        'note',        // Note aggiuntive
        'reason',      // Motivo dell'ordine
        'confirmed_at', // Data di conferma dell'ordine
        'hash_flag',   // Flag di hash per identificazione univoca
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
        'parent_order_id',
        'has_shortfall',
        'supplier_id',
        'customer_id',
        'occasional_customer_id', // ID del cliente occasionale, se presente
        'order_number_id',  // ID del numero d'ordine associato
        'total',      // Valore totale
        'ordered_at', // Data ordine
        'delivery_date', // Data prevista di consegna
        'registration_date', // Data registrazione magazzino
        'bill_number', // Numero bolla di consegna
        'shipping_address',
        'min_phase', // Fase minima di produzione
        'status',
        'note',
        'reason',
        'confirmed_at',
        'hash_flag',
    ];

    /**
     * Attributi da castare a un tipo specifico.
     */
    protected $casts    = [
        'ordered_at'        => 'datetime:d/m/Y',
        'delivery_date'     => 'date:d/m/Y',
        'registration_date' => 'date:d/m/Y',
        'has_shortfall' => 'bool',
        'hash_flag'    => 'boolean',
        'status'        => 'boolean',
        'note'         => 'string',
        'reason'       => 'string',
        'confirmed_at' => 'datetime',
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
     * Cliente occasionale associato all'ordine (se presente).
     */
    public function occasionalCustomer()
    {
        return $this->belongsTo(OccasionalCustomer::class);
    }

    /**
     * Numero dell'ordine associato.
     */
    public function orderNumber() { 
        return $this->belongsTo(OrderNumber::class); 
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

    /**
     * Registrazioni di magazzino collegate a questo ordine.
     */
    public function stockLevelLots()
    {
        return $this->belongsToMany(
            StockLevelLot::class,
            'order_stock_level'      // tabella pivot
        )
        ->withTimestamps()
        ->with('stockLevel');
    }

    
    /**
     * Relazione: Ordine -> molti ProductStockLevels (prodotti finiti da reso rivendibili).
     * Utile per verificare priorità di disponibilità prima di andare su componenti/PO.
     */
    public function productStockLevels()
    {
        return $this->hasMany(ProductStockLevel::class, 'order_id');
    }

    /**
     * Righe PO nate da questo ordine cliente
     */
    public function generatedSupplierItems(): HasMany
    {
        return $this->hasMany(
            OrderItem::class,
            'generated_by_order_customer_id'
        );
    }

    /**
     * Ottiene il numero dell'ordine associato.
     *
     * @return int|null
     */
    public function getNumberAttribute() { 
        return $this->orderNumber->number ?? null; 
    }

    /**
     * Ottiene il tipo dell'ordine associato.
     *
     * @return string|null
     */
    public function getTypeAttribute()   { 
        return $this->orderNumber->order_type ?? null; 
    }
}