<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Modello per la tabella 'customer_addresses'.
 *
 * Gestisce gli indirizzi multipli del cliente.
 */
class CustomerAddress extends Model
{
    use LogsActivity;

    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'customer_id',
        'type',
        'address',
        'city',
        'postal_code',
        'country',
    ];
    protected static $logName = 'customer_address';

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
            ->useLogName('customer_address'); // nome del log per distinguere
    }

    /**
     * Attributi assegnabili in massa.
     */
    protected $fillable = [
        'customer_id',
        'type',       // billing/shipping/other
        'address',
        'city',
        'postal_code',
        'country',
    ];

    /**
     * Cliente proprietario di questo indirizzo.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}