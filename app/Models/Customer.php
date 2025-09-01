<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
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
        'vat_number',
        'tax_code',
        'email',
        'phone',
        'is_active',
    ];

    protected static $logName = 'customer';

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
            ->useLogName('customer'); // nome del log per distinguere
    }
    
    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'company',
        'vat_number',
        'tax_code',
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
     * Indirizzo di spedizione principale.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function shippingAddress()
    {
        return $this->hasOne(CustomerAddress::class)
                    ->where('type', 'shipping');
    }

    /**
     * Ordini associati al cliente.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Prezzi per prodotto associati a questo cliente.
     */
    public function productPrices()
    {
        return $this->hasMany(CustomerProductPrice::class);
    }
}