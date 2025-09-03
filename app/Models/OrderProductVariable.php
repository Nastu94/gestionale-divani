<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scelta delle variabili su una riga d'ordine (slot FABRIC_MAIN),
 * con riferimento al componente reale risolto e snapshot dei prezzi applicati.
 */
class OrderProductVariable extends Model
{
    protected $fillable = [
        'order_item_id', 'slot', 'fabric_id', 'color_id', 'resolved_component_id',
        'surcharge_fixed_applied', 'surcharge_percent_applied', 'surcharge_total_applied', 'computed_at',
    ];

    protected $casts = [
        'surcharge_fixed_applied'   => 'decimal:2',
        'surcharge_percent_applied' => 'decimal:2',
        'surcharge_total_applied'   => 'decimal:2',
        'computed_at'               => 'datetime',
    ];

    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function fabric(): BelongsTo { return $this->belongsTo(Fabric::class); }
    public function color(): BelongsTo { return $this->belongsTo(Color::class); }
    public function resolvedComponent(): BelongsTo { return $this->belongsTo(Component::class, 'resolved_component_id'); }
}
