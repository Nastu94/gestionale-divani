<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order;

class OrderNumber extends Model
{   
    /**
     * La tabella associata al modello.
     *
     * @var string
     */
    protected $table = 'order_numbers';

    /**
     * Gli attributi che possono essere assegnati in massa.
     *
     * @var array
     */
    protected $fillable = [
        'order_type', 
        'number',
    ];

    /**
     * Cast degli attributi.
     *
     * @var array
     */
    protected $casts = [
        'number' => 'integer',
    ];

    /**
     * Indica se il modello utilizza le colonne di timestamp.
     *
     * @var bool
     */
    public $timestamps  = true;

    /**
     * La relazione con il modello Order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order() { 
        return $this->hasOne(Order::class); 
    }
}
