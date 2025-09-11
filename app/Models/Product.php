<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
     * Regole:
     * - percentuale → calcolata su prezzo base prodotto
     * - fisso       → calcolato come €/m × metri tessuto del prodotto
     *
     * Ordine di applicazione:
     * 1) Override coppia tessuto×colore (se presente):
     *      - unit_price → prezzo finale diretto
     *      - surcharge_type/value → unico delta rispetto a base (con regola percent/fixed sopra)
     * 2) Altrimenti surcharge TESSUTO → prima da pivot prodotto (se valorizzato), altrimenti da tabella 'fabrics'
     * 3) Poi surcharge COLORE → prima da pivot prodotto (se valorizzato), altrimenti da tabella 'colors'
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
        // 0) Base effettivo (listino/cliente) + fonte (per eventuale UI/debug)
        $meta       = $this->basePriceMetaFor($customerId, $atDate);
        $base       = $this->effectiveBasePriceFor($customerId, $atDate);
        $baseSource = $meta['source'] ?? null;

        // Metri di tessuto da usare per il calcolo dei "fixed"
        $meters = $this->resolveBaseFabricMeters();

        // 1) Override di COPPIA (se configurato)
        if ($fabricId && $colorId) {
            $pair = $this->fabricColorOverrides()
                ->where('fabric_id', $fabricId)
                ->where('color_id',  $colorId)
                ->first();

            if ($pair) {
                // 1a) unit_price diretto: bypassa ogni calcolo
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

                // 1b) surcharge_type/value: percent su base, fixed su metri
                if (isset($pair->surcharge_type, $pair->surcharge_value)) {
                    $delta = $this->applySurchargeWithContext($base, $pair->surcharge_type, $pair->surcharge_value, $meters);

                    // Manteniamo la shape storica: mettiamo il delta in color_surcharge e segnaliamo la fonte
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

        // 2) Sovrapprezzo TESSUTO: pivot → fallback 'fabrics'
        $fabricS  = 0.0;
        $fabricSrc = null;

        if ($fabricId) {
            $pf = $this->fabrics()->where('fabrics.id', $fabricId)->first();
            $type = null; $val = null;

            if ($pf) {
                $type = $pf->pivot->surcharge_type ?? null;
                $val  = $pf->pivot->surcharge_value ?? null;

                if (!is_null($type) || !is_null($val)) {
                    $fabricS  = $this->applySurchargeWithContext($base, $type, $val, $meters);
                    $fabricSrc = 'pivot';
                }
            }

            if ($fabricSrc === null) {
                $fab = $pf ?: Fabric::find($fabricId); // se non già caricato
                if ($fab && (Schema::hasColumn('fabrics', 'surcharge_type') || Schema::hasColumn('fabrics', 'surcharge_value'))) {
                    $fabricS  = $this->applySurchargeWithContext($base, $fab->surcharge_type ?? null, $fab->surcharge_value ?? null, $meters);
                    $fabricSrc = 'fabric';
                } else {
                    $fabricS  = 0.0;
                    $fabricSrc = 'none';
                }
            }
        }

        // 3) Sovrapprezzo COLORE: pivot → fallback 'colors'
        $colorS  = 0.0;
        $colorSrc = null;

        if ($colorId) {
            $pc = $this->colors()->where('colors.id', $colorId)->first();
            $type = null; $val = null;

            if ($pc) {
                $type = $pc->pivot->surcharge_type ?? null;
                $val  = $pc->pivot->surcharge_value ?? null;

                if (!is_null($type) || !is_null($val)) {
                    $colorS  = $this->applySurchargeWithContext($base, $type, $val, $meters);
                    $colorSrc = 'pivot';
                }
            }

            if ($colorSrc === null) {
                $col = $pc ?: Color::find($colorId);
                if ($col && (Schema::hasColumn('colors', 'surcharge_type') || Schema::hasColumn('colors', 'surcharge_value'))) {
                    $colorS  = $this->applySurchargeWithContext($base, $col->surcharge_type ?? null, $col->surcharge_value ?? null, $meters);
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

    /**
     * Ricava i metri di tessuto usati dal prodotto (quantità della riga TESSU in distinta).
     *
     * Ordine di risoluzione:
     * 1) Se il model ha una colonna/attributo 'base_fabric_qty_m' → usa quella.
     * 2) Altrimenti, cerca nella distinta del prodotto (product_components)
     *    la riga il cui componente appartiene alla categoria 'TESSU' e legge la quantità.
     *
     * @return float Metri (0.0 se non trovati)
     */
    protected function resolveBaseFabricMeters(): float
    {
        // 1) Attributo diretto (se esiste nella tabella products)
        if (array_key_exists('base_fabric_qty_m', $this->attributes ?? []) && !is_null($this->base_fabric_qty_m)) {
            return (float) $this->base_fabric_qty_m;
        }

        // 2) Fallback: leggi dalla distinta (product_components → components → component_categories)
        $qty = DB::table('product_components as pc')
            ->join('components as c', 'c.id', '=', 'pc.component_id')
            ->join('component_categories as cc', 'cc.id', '=', 'c.category_id')
            ->where('pc.product_id', $this->id)
            ->where('cc.code', 'TESSU')
            ->value('pc.quantity');

        return (float) ($qty ?? 0.0);
    }

    /**
     * Calcola il sovrapprezzo con contesto:
     * - 'percent' → percentuale del prezzo base prodotto
     * - 'fixed'   → importo €/m * metri di tessuto del prodotto
     *
     * @param  float       $base    Prezzo base prodotto (unitario)
     * @param  string|null $type    'percent'|'percentage'|'%'  oppure 'fixed'|'amount'|'€'|'eur'|null
     * @param  mixed       $value   Valore percentuale o €/m
     * @param  float       $meters  Metri di tessuto da applicare nel caso 'fixed'
     * @return float                Delta (€) calcolato
     */
    protected function applySurchargeWithContext(float $base, ?string $type, $value, float $meters): float
    {
        $v = is_null($value) ? 0.0 : (float) $value;
        $t = is_null($type) ? null : strtolower(trim($type));

        return match ($t) {
            'percent', 'percentage', '%' => $base * ($v / 100),

            // FIX: se non ho metri (o non hanno senso per quella regola), tratto come importo assoluto
            'fixed', 'amount', '€', 'eur', null, '' =>
                ($meters > 0.0 ? $v * $meters : $v),

            default => ($meters > 0.0 ? $v * $meters : $v),
        };
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
