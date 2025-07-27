<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ProductionPhase;

/**
 * Pivot model → tabella component_category_phase
 *
 * @property int              $category_id
 * @property ProductionPhase  $phase
 */
class ComponentCategoryPhase extends Model
{
    /** @var string  */
    protected $table = 'component_category_phase';

    /** @var bool  */
    public $incrementing = false;            // PK composta

    /** @var array<string,string>  */
    protected $casts = [
        'phase' => ProductionPhase::class,   // enum cast
    ];

    /** @var array<int,string> */
    protected $fillable = ['category_id','phase'];

    /* ─────────── Relationships ─────────── */

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComponentCategory::class, 'category_id');
    }
}
