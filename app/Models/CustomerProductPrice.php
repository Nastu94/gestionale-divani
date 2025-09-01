<?php
// app/Models/CustomerProductPrice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Gestisce il prezzo per coppia (product, customer) con validità temporale.
 * Ogni riga è una "versione" non sovrapposta ad altre dello stesso binomio.
 */
class CustomerProductPrice extends Model
{
    use LogsActivity;

    /** @var string[] $fillable Campi mass-assignable (controller convalidate già i dati) */
    protected $fillable = [
        'product_id',
        'customer_id',
        'price',
        'currency',
        'valid_from',
        'valid_to',
        'notes',
    ];

    /** @var array $casts Cast automatici per date e numeri */
    protected $casts = [
        'price'      => 'decimal:2',
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    /**
     * Relazione: versione appartiene a un Prodotto.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relazione: versione appartiene a un Cliente.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Configurazione Activity Log: logga solo differenze rilevanti.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->useLogName('customer_product_prices')
            ->logFillable()
            ->setDescriptionForEvent(function (string $eventName) {
                return match ($eventName) {
                    'created' => 'Creato prezzo cliente-prodotto',
                    'updated' => 'Aggiornato prezzo cliente-prodotto',
                    'deleted' => 'Eliminato prezzo cliente-prodotto',
                    default   => "Evento {$eventName} su prezzo cliente-prodotto",
                };
            });
    }
}
