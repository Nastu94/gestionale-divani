<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $order_item_id
 * @property int    $order_customer_id
 * @property float  $quantity
 */
class PoReservation extends Model
{
    protected $fillable = [
        'order_item_id',
        'order_customer_id',
        'quantity',
    ];

    /* Relations */
    public function orderItem() { 
        return $this->belongsTo(OrderItem::class); 
    }

    public function orderCustomer() { 
        return $this->belongsTo(Order::class, 'order_customer_id'); 
    }
}
