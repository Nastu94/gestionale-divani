<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override eccezionale per PRODOTTO su coppia (tessuto×colore).
 * Ha precedenza su override singoli e su default globali.
 */
class ProductFabricColorOverride extends Model
{
    protected $table = 'product_fabric_color_overrides';

    protected $fillable = [
        'product_id', 'fabric_id', 'color_id', 'surcharge_type', 'surcharge_value', 'note',
    ];

    protected $casts = [
        'surcharge_value' => 'decimal:2',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function fabric(): BelongsTo { return $this->belongsTo(Fabric::class); }
    public function color(): BelongsTo { return $this->belongsTo(Color::class); }
}
