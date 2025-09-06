<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Component;
use App\Models\Fabric;
use App\Models\Color;
use App\Models\ProductFabricColorOverride;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * Modello per la tabella 'products'.
 *
 * Rappresenta i modelli finiti di divano (SKU commerciali).
 */
class Product extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'sku',        // Codice prodotto
        'name',       // Nome prodotto
        'description',// Descrizione dettagliata
        'price',      // Prezzo unitario
        'is_active',  // Disponibilità
    ];

    protected static $logName = 'product';

    /**
     * Configura le opzioni di logging per questo modello.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        // Logga tutti gli attributi 'fillable', registra solo i cambiamenti
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('product'); // nome del log per distinguere
    }
    
    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'sku',        // Codice prodotto
        'name',       // Nome prodotto
        'description',// Descrizione dettagliata
        'price',      // Prezzo unitario
        'is_active',  // Disponibilità
    ];

    /**
     * Relazione molti a molti con Component tramite distinta base.
     */
    public function components()
    {
        return $this->belongsToMany(Component::class, 'product_components')
                    ->withPivot('quantity');
    }

    /**
     * Prodotti varianti di questo modello.
     */
    public function variants()
    {
        return $this->hasMany(self::class, 'variant_of');
    }

    /**
     * Modello padre se è una variante.
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'variant_of');
    }

    /**
     * Prezzi per cliente associati a questo prodotto.
     */
    public function customerPrices()
    {
        return $this->hasMany(CustomerProductPrice::class);
    }

    /**
     * RELAZIONI per variabili (tessuto/colore) e override.
     * Non alteriamo altro codice già presente nel Model.
     */
    public function fabrics(): BelongsToMany
    {
        // Whitelist + eventuali override per-prodotto sul tessuto
        return $this->belongsToMany(Fabric::class, 'product_fabrics')
            ->withPivot(['surcharge_type','surcharge_value','is_default'])
            ->withTimestamps();
    }

    public function colors(): BelongsToMany
    {
        // Whitelist + eventuali override per-prodotto sul colore
        return $this->belongsToMany(Color::class, 'product_colors')
            ->withPivot(['surcharge_type','surcharge_value','is_default'])
            ->withTimestamps();
    }

    public function fabricColorOverrides(): HasMany
    {
        // Override eccezionali su coppia tessuto×colore per questo prodotto
        return $this->hasMany(ProductFabricColorOverride::class);
    }

    /**
     * Garantisce la presenza in BOM di una riga "placeholder TESSU base (0×0)"
     * impostando in pivot ->quantity i metri di tessuto richiesti per 1 unità di prodotto.
     *
     * @param  float  $meters  Metri unitari da salvare in product_components.quantity
     * @throws \RuntimeException Se non esiste alcun componente base disponibile
     */
    public function ensureTessuPlaceholderWithMeters(float $meters): void
    {
        // 1) Trova il "primo" componente base TESSU (0×0) secondo le tue regole
        $placeholder = $this->findFirstBaseTessuComponent();

        // 2) Prepara i dati per la pivot della BOM: quantity = metri unitari
        $pivot = [
            'quantity' => $meters, // <-- qui salviamo i metri richiesti per 1 unità
        ];

        // 3) Se la pivot ha colonne opzionali, le gestiamo senza dare errore
        if (Schema::hasColumn('product_components', 'is_variable')) {
            $pivot['is_variable'] = true; // segna la riga come "slot variabile"
        }
        if (Schema::hasColumn('product_components', 'variable_slot')) {
            $pivot['variable_slot'] = 'TESSU'; // nome slot, coerente con la tua UI/logica
        }

        // 4) Inserisce senza rimuovere altre righe ed aggiorna se già esiste
        $this->components()->syncWithoutDetaching([$placeholder->id => $pivot]);
        $this->components()->updateExistingPivot($placeholder->id, $pivot);
    }

    /**
     * Individua il primo componente TESSU "base (0×0)".
     * La logica è resiliente: usa "is_base" se disponibile, altrimenti prova
     * a inferire la base da fabric/color con surcharge=0. In mancanza, lancia eccezione.
     *
     * @return \App\Models\Component
     * @throws \RuntimeException
     */
    protected function findFirstBaseTessuComponent(): Component
    {
        $q = Component::query()->where('is_active', true);

        // Preferenza: se hai un flag "is_base" sulla tabella components lo usiamo.
        if (Schema::hasColumn('components', 'is_base')) {
            $q->where('is_base', true);
        } else {
            // Altrimenti proviamo a dedurre "base" da relazioni fabric/color con surcharge a 0.
            // Queste whereHas presuppongono che il Model Component abbia relazioni fabric() e color().
            if (method_exists(Component::class, 'fabric')) {
                $q->whereHas('fabric', fn($qq) => $qq->where('surcharge_value', 0));
            }
            if (method_exists(Component::class, 'color')) {
                $q->whereHas('color', fn($qq) => $qq->where('surcharge_value', 0));
            }
        }

        // Se hai una colonna "category" (o simile) per classificare i TESSU, la applichiamo.
        if (Schema::hasColumn('components', 'category')) {
            $q->where('category', 'TESSU');
        }

        /** @var Component|null $placeholder */
        $placeholder = $q->orderBy('id', 'asc')->first();

        if (! $placeholder) {
            throw new \RuntimeException(
                'Nessun componente base TESSU (0×0) attivo trovato. ' .
                'Crea almeno un componente base per poter salvare il prodotto.'
            );
        }

        return $placeholder;
    }
}