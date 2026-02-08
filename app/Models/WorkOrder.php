<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    protected $fillable = [
        'order_id','phase','year','number','issued_at','created_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'phase'     => 'integer',
        'year'      => 'integer',
        'number'    => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WorkOrderLine::class);
    }
}
