<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use App\Enums\ProductionPhase;
use App\Models\ComponentCategoryPhase;

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

    /** Pivot 1-N */
    public function phaseLinks(): HasMany
    {
        return $this->hasMany(ComponentCategoryPhase::class, 'category_id');
    }

    /**
     * Collection delle fasi come enum.
     *
     * @return Collection<int, ProductionPhase>
     */
    public function phasesEnum(): Collection
    {
        return $this->phaseLinks
                    ->pluck('phase')               // Collection<ProductionPhase>
                    ->unique()
                    ->values();                    // indicizzazione pulita
    }

    /**
     * Sincronizza le fasi (array di int 1-5) con la pivot.
     *
     * @param array<int,int> $phases
     */
    public function syncPhases(array $phases): void
    {
        $phases = array_unique(array_map('intval', $phases));

        // elimina quelle non più presenti
        $this->phaseLinks()
             ->whereNotIn('phase', $phases)
             ->delete();

        // aggiungi le nuove mancanti
        foreach ($phases as $p) {
            $this->phaseLinks()->firstOrCreate(['phase' => $p]);
        }
    }
    
    /**
     * Forzare il maiuscolo sul codice.
     *
     * @param string $value
     */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper($value);
    }
}
