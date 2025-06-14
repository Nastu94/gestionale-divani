<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Modello pivot per la relazione component_supplier.
 */
class ComponentSupplier extends Pivot
{
    /**
     * Nome della tabella pivot.
     *
     * @var string
     */
    protected $table = 'component_supplier';

    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'component_id',
        'supplier_id',
    ];
}