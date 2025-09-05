<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * Modello Eloquent per i colori.
 * Contiene i default di maggiorazione e le relazioni con prodotti e componenti.
 */
class Color extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name', 'code', 'hex', 'surcharge_type', 'surcharge_value', 'active',
    ];

    protected $casts = [
        'active' => 'bool',
        'surcharge_value' => 'decimal:2',
    ];

    protected static $logAttributes = [
        'name', 'code', 'hex', 'surcharge_type', 'surcharge_value', 'active',
    ];

    protected static $logName = 'color';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('color')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }



    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_colors')
            ->withPivot(['surcharge_type','surcharge_value','is_default'])
            ->withTimestamps();
    }

    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }
}
