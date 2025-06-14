<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modello per la tabella 'customers'.
 *
 * Contiene l'anagrafica clienti.
 */
class Customer extends Model
{
    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'company',
        'email',
        'phone',
        'is_active',
    ];

    /**
     * Indirizzi multipli del cliente.
     */
    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Ordini associati al cliente.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}