<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StockLevelLot;

class LotNumber extends Model
{
    protected $fillable = [
        'code',
        'status',
        'reserved_by',
        'stock_level_lot_id',
    ];

    /* relazione inversa */
    public function stockLevelLot()
    {
        return $this->hasOne(StockLevelLot::class);
    }
}
