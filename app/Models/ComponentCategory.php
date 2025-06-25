<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modello Eloquent per la tabella 'component_categories'.
 *
 * @property int    $id
 * @property string $code
 * @property string $name
 * @property ?string $description
 */
class ComponentCategory extends Model
{
    /** Attributi assegnabili - mass assignment */
    protected $fillable = [
        'code', 
        'name', 
        'description'
    ];

    /**
     * Relazione 1-N con Component.
     *
     * @return HasMany
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class, 'category_id');
    }
}
