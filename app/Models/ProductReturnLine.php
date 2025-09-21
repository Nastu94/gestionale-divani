<?php
/**
 * Model: ProductReturnLine (riga reso)
 *
 * Descrive il dettaglio di un reso: prodotto finito + eventuali variabili,
 * quantità e metadati. Se restock=true, collega (opzionalmente) la PSL creata.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReturnLine extends Model
{
    use HasFactory;

    /** @var array<int, string> Campi assegnabili in massa. */
    protected $fillable = [
        'product_return_id',
        'product_id',
        'fabric_id',
        'color_id',
        'quantity',
        'restock',
        'warehouse_id',
        'condition',
        'reason',
        'notes',
        'product_stock_level_id',
    ];

    /** @var array<string, string> Cast per tipi comodi. */
    protected $casts = [
        'quantity' => 'integer',
        'restock'  => 'boolean',
    ];

    /** Testata reso. */
    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class);
    }

    /** Prodotto (divano finito). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Tessuto (variabile). */
    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    /** Colore (variabile). */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /** Magazzino di rientro (di norma MG-RETURN quando restock=true). */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Eventuale riga di giacenza prodotto finito creata per questo reso (quando restock=true). */
    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(ProductStockLevel::class, 'product_stock_level_id');
    }
}
