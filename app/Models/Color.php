<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modello Eloquent per i colori.
 * Contiene i default di maggiorazione e le relazioni con prodotti e componenti.
 */
class Color extends Model
{
    protected $fillable = [
        'name', 'code', 'hex', 'surcharge_type', 'surcharge_value', 'active',
    ];

    protected $casts = [
        'active' => 'bool',
        'surcharge_value' => 'decimal:2',
    ];

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
