<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model DDT (Documento di Trasporto).
 */
class Ddt extends Model
{
    /** @var array<int, string> */
    protected $fillable = [
        'order_id', 'year', 'number', 'issued_at',
        'carrier_name', 'transport_reason', 'packages', 'weight',
        'goods_appearance', 'port', 'transport_started_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'issued_at' => 'date',
        'transport_started_at' => 'datetime',
    ];

    /**
     * Ordine collegato.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Righe del DDT.
     */
    public function rows(): HasMany
    {
        return $this->hasMany(DdtRow::class);
    }
}
