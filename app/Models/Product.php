<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

use App\Models\Component;
use App\Models\Fabric;
use App\Models\Color;
use App\Models\ProductFabricColorOverride;
use App\Support\Pricing\CustomerPriceResolver;

/**
 * Modello per la tabella 'products'.
 * Prezzo base effettivo delegato a CustomerPriceResolver.
 * Sovrapprezzi tessuto/colore con fallback ai valori base delle tabelle fabrics/colors.
 */
class Product extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logAttributes = [
        'sku', 'name', 'description', 'price', 'is_active',
    ];
    protected static $logName = 'product';

    protected $fillable = [
        'sku', 'name', 'description', 'price', 'is_active',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('product');
    }

    /* ───────────────────────── Relazioni ───────────────────────── */

    /** Distinta base con pivot (quantity, is_variable, variable_slot). */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class, 'product_components')
            ->withPivot(['quantity', 'is_variable', 'variable_slot']);
    }

    /** Whitelist tessuti (pivot: surcharge_type/surcharge_value/is_default). */
    public function fabrics(): BelongsToMany
    {
        return $this->belongsToMany(Fabric::class, 'product_fabrics')
            ->withPivot(['surcharge_type', 'surcharge_value', 'is_default'])
            ->withTimestamps();
    }

    /** Whitelist colori (pivot: surcharge_type/surcharge_value/is_default). */
    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(Color::class, 'product_colors')
            ->withPivot(['surcharge_type', 'surcharge_value', 'is_default'])
            ->withTimestamps();
    }

    /** Override su coppia (tessuto×colore) per questo prodotto. */
    public function fabricColorOverrides(): HasMany
    {
        return $this->hasMany(ProductFabricColorOverride::class);
    }

    /** Varianti e parent (se usi modelli-figli). */
    public function variants()
    {
        return $this->hasMany(self::class, 'variant_of');
    }
    public function parent()
    {
        return $this->belongsTo(self::class, 'variant_of');
    }

    /** Prezzi per cliente associati (se ti servono altrove). */
    public function customerPrices()
    {
        return $this->hasMany(CustomerProductPrice::class);
    }

    /* ───────────────────────── Variabile BOM (default fabric/color) ───────────────────────── */

    /**
     * Restituisce il componente “variabile” della BOM (slot opzionale).
     * ATTENZIONE: la tabella product_components NON ha una colonna id → niente orderBy su pivot id.
     */
    public function variableComponent(?string $slot = null): ?Component
    {
        return $this->components()
            ->wherePivot('is_variable', 1)
            ->when($slot, fn ($q) => $q->wherePivot('variable_slot', $slot))
            ->first();
    }

    /** ID tessuto default risalendo dal componente variabile; nessun uso di is_default su pivot. */
    public function defaultFabricId(): ?int
    {
        $c = $this->variableComponent();
        return ($c && !is_null($c->fabric_id)) ? (int) $c->fabric_id : null;
    }

    /** ID colore default risalendo dal componente variabile; nessun uso di is_default su pivot. */
    public function defaultColorId(): ?int
    {
        $c = $this->variableComponent();
        return ($c && !is_null($c->color_id)) ? (int) $c->color_id : null;
    }

    /** Liste ID consentiti da whitelist. */
    public function fabricIds(): array
    {
        return $this->fabrics()->pluck('fabrics.id')->map(fn ($i) => (int) $i)->all();
    }
    public function colorIds(): array
    {
        return $this->colors()->pluck('colors.id')->map(fn ($i) => (int) $i)->all();
    }

    /* ───────────────────────── Prezzo base: delega al Resolver ───────────────────────── */

    /**
     * Meta del prezzo base effettivo per (cliente, data) dal CustomerPriceResolver.
     * Ritorna array con chiavi: price(string), source, version_id, valid_from, valid_to oppure null.
     */
    public function basePriceMetaFor(?int $customerId, $atDate = null): ?array
    {
        /** @var CustomerPriceResolver $resolver */
        $resolver = app(CustomerPriceResolver::class);
        return $resolver->resolve((int) $this->id, $customerId, $atDate);
    }

    /**
     * Prezzo base effettivo come float.
     * Fallback al campo 'price' del prodotto se il resolver non restituisce nulla.
     */
    public function effectiveBasePriceFor(?int $customerId, $atDate = null): float
    {
        $meta = $this->basePriceMetaFor($customerId, $atDate);
        if (is_array($meta) && array_key_exists('price', $meta) && $meta['price'] !== null) {
            return (float) $meta['price'];
        }
        return (float) ($this->price ?? 0);
    }

    /* ───────────────────────── Calcolo con variabili ───────────────────────── */

    /**
     * Applica sovrapprezzo al base, gestendo tipi 'percent'/'percentage'/'%' e 'fixed' (o null=fisso).
     */
    protected function applySurcharge(float $base, ?string $type, $value): float
    {
        $v = is_null($value) ? 0.0 : (float) $value;
        return match ($type) {
            'percent', 'percentage', '%' => $base * ($v / 100),
            'fixed', 'amount', '€', 'eur', null, '' => $v,
            default => $v, // fallback trattato come fisso
        };
    }

    /**
     * Ritorna il dettaglio prezzo per (tessuto, colore, cliente, data).
     * Ordine di applicazione:
     * 1) Override coppia tessuto×colore: unit_price oppure surcharge rispetto al base.
     * 2) Altrimenti, surcharge tessuto → da pivot; se NULL, fallback ai campi su 'fabrics'.
     * 3) Poi surcharge colore → da pivot; se NULL, fallback ai campi su 'colors'.
     *
     * @return array{
     *   base_price:float,
     *   base_source:?string,
     *   fabric_surcharge:float,
     *   fabric_source:?string,
     *   color_surcharge:float,
     *   color_source:?string,
     *   unit_price:float
     * }
     */
    public function pricingBreakdown(
        ?int $fabricId,
        ?int $colorId,
        ?int $customerId = null,
        $atDate = null
    ): array {
        // 0) Base effettivo dal resolver (con meta opzionale per debug/UI)
        $meta = $this->basePriceMetaFor($customerId, $atDate);
        $base = $this->effectiveBasePriceFor($customerId, $atDate);
        $baseSource = $meta['source'] ?? null;

        // 1) Override di coppia (se configurato)
        if ($fabricId && $colorId) {
            $pair = $this->fabricColorOverrides()
                ->where('fabric_id', $fabricId)
                ->where('color_id',  $colorId)
                ->first();

            if ($pair) {
                if (isset($pair->unit_price) && $pair->unit_price !== null) {
                    return [
                        'base_price'        => $base,
                        'base_source'       => $baseSource,
                        'fabric_surcharge'  => 0.0,
                        'fabric_source'     => 'pair-override',
                        'color_surcharge'   => 0.0,
                        'color_source'      => 'pair-override',
                        'unit_price'        => (float) $pair->unit_price,
                    ];
                }

                if (isset($pair->surcharge_type, $pair->surcharge_value)) {
                    $delta = $this->applySurcharge($base, $pair->surcharge_type, $pair->surcharge_value);
                    return [
                        'base_price'        => $base,
                        'base_source'       => $baseSource,
                        'fabric_surcharge'  => 0.0,
                        'fabric_source'     => 'pair-override',
                        'color_surcharge'   => $delta, // unico delta della coppia
                        'color_source'      => 'pair-override',
                        'unit_price'        => $base + $delta,
                    ];
                }
            }
        }

        // 2) Sovrapprezzo TESSUTO: pivot → fallback tabella fabrics
        $fabricS = 0.0;
        $fabricSrc = null;

        if ($fabricId) {
            $pf = $this->fabrics()->where('fabrics.id', $fabricId)->first();
            $type = null; $val = null;

            if ($pf) {
                $type = $pf->pivot->surcharge_type ?? null;
                $val  = $pf->pivot->surcharge_value ?? null;
                if (!is_null($type) || !is_null($val)) {
                    $fabricS  = $this->applySurcharge($base, $type, $val);
                    $fabricSrc = 'pivot';
                }
            }

            if ($fabricSrc === null) {
                $fab = $pf ?: Fabric::find($fabricId); // se non già caricato
                if ($fab && (Schema::hasColumn('fabrics', 'surcharge_type') || Schema::hasColumn('fabrics', 'surcharge_value'))) {
                    $fabricS  = $this->applySurcharge($base, $fab->surcharge_type ?? null, $fab->surcharge_value ?? null);
                    $fabricSrc = 'fabric';
                } else {
                    $fabricS  = 0.0;
                    $fabricSrc = 'none';
                }
            }
        }

        // 3) Sovrapprezzo COLORE: pivot → fallback tabella colors
        $colorS = 0.0;
        $colorSrc = null;

        if ($colorId) {
            $pc = $this->colors()->where('colors.id', $colorId)->first();
            $type = null; $val = null;

            if ($pc) {
                $type = $pc->pivot->surcharge_type ?? null;
                $val  = $pc->pivot->surcharge_value ?? null;
                if (!is_null($type) || !is_null($val)) {
                    $colorS  = $this->applySurcharge($base, $type, $val);
                    $colorSrc = 'pivot';
                }
            }

            if ($colorSrc === null) {
                $col = $pc ?: Color::find($colorId);
                if ($col && (Schema::hasColumn('colors', 'surcharge_type') || Schema::hasColumn('colors', 'surcharge_value'))) {
                    $colorS  = $this->applySurcharge($base, $col->surcharge_type ?? null, $col->surcharge_value ?? null);
                    $colorSrc = 'color';
                } else {
                    $colorS  = 0.0;
                    $colorSrc = 'none';
                }
            }
        }

        return [
            'base_price'        => $base,
            'base_source'       => $baseSource,
            'fabric_surcharge'  => $fabricS,
            'fabric_source'     => $fabricSrc,
            'color_surcharge'   => $colorS,
            'color_source'      => $colorSrc,
            'unit_price'        => $base + $fabricS + $colorS,
        ];
    }

    /**
     * Adapter “semplice” per chi già usa questo metodo:
     * restituisce le 4 chiavi storiche.
     */
    public function unitPriceFor(
        ?int $fabricId,
        ?int $colorId,
        ?int $customerId = null,
        $atDate = null
    ): array {
        $b = $this->pricingBreakdown($fabricId, $colorId, $customerId, $atDate);
        return [
            'base_price'       => $b['base_price'],
            'fabric_surcharge' => $b['fabric_surcharge'],
            'color_surcharge'  => $b['color_surcharge'],
            'unit_price'       => $b['unit_price'],
        ];
    }

    /* ───────────────────────── Utility placeholder TESSU (opzionale tua logica) ───────────────────────── */

    /**
     * Garantisce la presenza in BOM di una riga “slot TESSU” con i metri in pivot->quantity.
     */
    public function ensureTessuPlaceholderWithMeters(float $meters): void
    {
        $placeholder = $this->findFirstBaseTessuComponent();

        $pivot = ['quantity' => $meters];
        if (Schema::hasColumn('product_components', 'is_variable')) {
            $pivot['is_variable'] = true;
        }
        if (Schema::hasColumn('product_components', 'variable_slot')) {
            $pivot['variable_slot'] = 'TESSU';
        }

        $this->components()->syncWithoutDetaching([$placeholder->id => $pivot]);
        $this->components()->updateExistingPivot($placeholder->id, $pivot);
    }

    /**
     * Individua un componente base per TESSU secondo le tue regole.
     */
    protected function findFirstBaseTessuComponent(): Component
    {
        $q = Component::query()->where('is_active', true);

        if (Schema::hasColumn('components', 'is_base')) {
            $q->where('is_base', true);
        } else {
            if (Schema::hasColumn('components', 'category')) {
                $q->where('category', 'TESSU');
            }
        }

        $placeholder = $q->orderBy('id', 'asc')->first();

        if (!$placeholder) {
            throw new \RuntimeException(
                'Nessun componente base TESSU attivo trovato. Crea almeno un componente base.'
            );
        }

        return $placeholder;
    }
}
