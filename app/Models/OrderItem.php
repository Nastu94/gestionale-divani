<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItemShortfall;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modello per la tabella 'order_items'.
 *
 * Righe di dettaglio per ogni ordine.
 */
class OrderItem extends Model
{
    /**
     * Attributi assegnabili in massa.
     */
    protected $fillable = [
        'order_id',
        'product_id',   // ID del prodotto associato, se presente ordine cliente
        'component_id', // ID del componente associato, se presente ordine fornitore
        'quantity',   // Quantità ordinata
        'unit_price', // Prezzo unitario
        'generated_by_order_customer_id', // ID dell'ordine cliente che ha generato questa riga, se applicabile
    ];

    /**
     * Ordine di appartenenza.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Componente associato alla riga ordine.
     */
    public function component()
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Prodotto associato alla riga ordine.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    /**
     * Short-fall (quantità non consegnata) associato a questa riga.
     * Ritorna null se la riga è stata interamente evasa.
     */
    public function shortfall()
    {
        return $this->hasOne(OrderItemShortfall::class);
    }

    /**
     * Relazione con la riga riservata per l'ordine cliente.
     * Ritorna la riga riservata se esiste, altrimenti null.
     */
    public function poReservations()
    {
        return $this->hasMany(PoReservation::class);
    }

    /**
     * OC che ha originato questa riga PO (nullable)
     */
    public function generatedByOc(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'generated_by_order_customer_id');
    }
}