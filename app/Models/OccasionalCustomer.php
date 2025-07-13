<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Modello per la tabella 'occasional_customers'.
 *
 * Contiene i “clienti saltuari” creati on-the-fly nel
 * flusso Ordini Cliente (permesso orders.customer.create).
 */
class OccasionalCustomer extends Model
{
    use LogsActivity;

    /** ---------------- Mass assignment ---------------- */
    protected $fillable = [
        'company',
        'vat_number',
        'tax_code',
        'address',
        'city',
        'postal_code',
        'province',
        'country',
        'email',
        'phone',
        'note',
    ];

    /** ---------------- Cast ---------------- */
    protected $casts = [
        'company' => 'string',
    ];

    /** ---------------- Relazioni ---------------- */

    /**
     * Ordini collegati a questo cliente “guest”.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** ---------------- LogsActivity ---------------- */

    protected static $logAttributes = [
        'company',
        'vat_number',
        'tax_code',
        'email',
        'phone',
    ];

    protected static $logName = 'occasional_customer';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('occasional_customer');
    }

    /** ---------------- Accessor ---------------- */

    /**
     * Etichetta da mostrare nei dropdown.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->company;
    }
}
