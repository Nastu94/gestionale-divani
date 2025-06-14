<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modello per la tabella 'customers'.
 *
 * Contiene l'anagrafica clienti.
 */
class Customer extends Model
{
    use LogsActivity;

    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'company',
        'email',
        'phone',
        'is_active',
    ];

    protected static $logName = 'customer';

    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
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