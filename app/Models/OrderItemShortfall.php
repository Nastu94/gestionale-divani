<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\OrderItem;

class OrderItemShortfall extends Model
{
    protected $fillable = [
        'order_item_id',
        'quantity',
        'note',
    ];

    /* relazione inversa alla riga d’ordine */
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
