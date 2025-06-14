<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Modello pivot per la relazione product_components.
 */
class ProductComponent extends Pivot
{
    /**
     * Nome della tabella pivot.
     *
     * @var string
     */
    protected $table = 'product_components';

    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'product_id',
        'component_id',
        'quantity',
    ];
}