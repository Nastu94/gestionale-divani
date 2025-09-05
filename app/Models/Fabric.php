<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Modello Eloquent per i tessuti.
 * Contiene i default di maggiorazione e le relazioni con prodotti e componenti.
 */
class Fabric extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name', 'code', 'surcharge_type', 'surcharge_value', 'active',
    ];

    protected $casts = [
        'active' => 'bool',
        'surcharge_value' => 'decimal:2',
    ];

    protected static $logAttributes = [
        'name', 'code', 'surcharge_type', 'surcharge_value', 'active',
    ];

    protected static $logName = 'fabric';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('fabric')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Prodotti che ammettono questo tessuto (whitelist per-prodotto). */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_fabrics')
            ->withPivot(['surcharge_type','surcharge_value','is_default'])
            ->withTimestamps();
    }

    /** Componenti (SKU) che rappresentano questo tessuto a magazzino (per combinazione con color). */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }
}
