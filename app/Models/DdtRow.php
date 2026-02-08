<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model riga DDT.
 */
class DdtRow extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'ddt_id', 'order_item_id', 'quantity', 'unit_price', 'vat',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'vat' => 'int',
    ];

    /**
     * DDT padre.
     */
    public function ddt(): BelongsTo
    {
        return $this->belongsTo(Ddt::class);
    }

    /**
     * Riga ordine collegata.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
