<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modello per la tabella 'orders'.
 *
 * Rappresenta ordini di acquisto o produzione.
 */
class Order extends Model
{
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