<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modello per la tabella 'customer_addresses'.
 *
 * Gestisce gli indirizzi multipli del cliente.
 */
class CustomerAddress extends Model
{
    /**
     * Attributi assegnabili in massa.
     */
    protected $fillable = [
        'customer_id',
        'type',       // billing/shipping/other
        'address',
        'city',
        'postal_code',
        'country',    // Nazione
    ];

    /**
     * Cliente proprietario di questo indirizzo.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}