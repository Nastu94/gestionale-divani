<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modello per la tabella 'stock_reservations'.
 *
 * Impegni di stock legati ad ordini cliente.
 */
class StockReservation extends Model
{
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