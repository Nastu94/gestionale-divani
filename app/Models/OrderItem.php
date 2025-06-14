<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use App\Models\Product;
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
        'product_id',
        'quantity',   // Quantità ordinata
        'unit_price', // Prezzo unitario
    ];

    /**
     * Ordine di appartenenza.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Prodotto associato alla riga ordine.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}