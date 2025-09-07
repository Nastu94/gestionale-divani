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

    /* ============================== RELAZIONI ============================== */

    /**
     * Relazione molti a molti con Component tramite distinta base.
     */
    public function components()
    {
        return $this->belongsToMany(Component::class, 'product_components')
            ->withPivot(['quantity', 'is_variable', 'variable_slot']);
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


    /* ===================== BOM: PLACEHOLDER & VARIABILE ===================== */

    /**
     * Garantisce una riga placeholder TESSU (0×0) in BOM con i metri unitari.
     */
    public function ensureTessuPlaceholderWithMeters(float $meters): void
    {
        $placeholder = $this->findFirstBaseTessuComponent();

        $pivot = ['quantity' => $meters];
        if (Schema::hasColumn('product_components', 'is_variable'))   $pivot['is_variable']   = true;
        if (Schema::hasColumn('product_components', 'variable_slot')) $pivot['variable_slot'] = 'TESSU';

        $this->components()->syncWithoutDetaching([$placeholder->id => $pivot]);
        $this->components()->updateExistingPivot($placeholder->id, $pivot);
    }

    /**
     * Trova un componente base TESSU attivo (fallback euristico se non c'è flag).
     */
    protected function findFirstBaseTessuComponent(): Component
    {
        $q = Component::query()->where('is_active', true);

        if (Schema::hasColumn('components', 'is_base')) {
            $q->where('is_base', true);
        } else {
            if (method_exists(Component::class, 'fabric')) {
                $q->whereHas('fabric', fn($qq) => $qq->where('surcharge_value', 0));
            }
            if (method_exists(Component::class, 'color')) {
                $q->whereHas('color', fn($qq) => $qq->where('surcharge_value', 0));
            }
        }

        if (Schema::hasColumn('components', 'category')) {
            $q->where('category', 'TESSU');
        }

        $placeholder = $q->orderBy('id', 'asc')->first();
        if (!$placeholder) {
            throw new \RuntimeException(
                'Nessun componente base TESSU (0×0) attivo trovato.'
            );
        }
        return $placeholder;
    }

    /**
     * Restituisce il componente variabile della BOM (slot).
     */
    public function variableComponent(?string $slot = null): ?Component
    {
        $q = $this->components()->wherePivot('is_variable', 1);

        if ($slot !== null) {
            $q->wherePivot('variable_slot', $slot);
        }

        // ⚠️ niente product_components.id: non esiste
        return $q->orderBy('product_components.component_id','asc')->first();
    }

    /* ===================== DEFAULT VARIABILI (SOLO BOM) ===================== */

    /**
     * Default fabric_id preso **solo** dalla BOM (component.fabric_id).
     */
    public function defaultFabricId(): ?int
    {
        $c = $this->variableComponent();
        return ($c && $c->fabric_id) ? (int) $c->fabric_id : null;
    }

    /**
     * Default color_id preso **solo** dalla BOM (component.color_id).
     */
    public function defaultColorId(): ?int
    {
        $c = $this->variableComponent();
        return ($c && $c->color_id) ? (int) $c->color_id : null;
    }

    /**
     * ID whitelist disponibili per UI (non usati per i default).
     */
    public function fabricIds(): array
    {
        return $this->fabrics()->pluck('fabrics.id')->map(fn($i)=>(int)$i)->all();
    }

    public function colorIds(): array
    {
        return $this->colors()->pluck('colors.id')->map(fn($i)=>(int)$i)->all();
    }

    /* ===================== PREZZI ===================== */

    /**
     * Prezzo base effettivo con eventuale prezzo cliente.
     */
    public function effectiveBasePriceFor(?int $customerId): float
    {
        if ($customerId) {
            $cp = $this->customerPrices()
                ->where('customer_id', $customerId)
                ->orderByDesc('id')
                ->first();
            if ($cp && $cp->price !== null) {
                return (float) $cp->price;
            }
        }
        return (float) ($this->price ?? 0);
    }

    /**
     * Calcola il prezzo unitario per tessuto/colore (con override coppia,
     * pivot-override e fallback ai valori di fabrics/colors se pivot è NULL).
     *
     * @return array{base_price:float,fabric_surcharge:float,color_surcharge:float,unit_price:float}
     */
    public function unitPriceFor(?int $fabricId, ?int $colorId, ?int $customerId = null): array
    {
        $base    = $this->effectiveBasePriceFor($customerId);
        $fabricS = 0.0;
        $colorS  = 0.0;

        // 1) Override specifico per coppia (può impostare prezzo fisso o delta)
        $pair = null;
        if ($fabricId && $colorId) {
            $pair = $this->fabricColorOverrides()
                ->where('fabric_id', $fabricId)
                ->where('color_id',  $colorId)
                ->first();
        }
        if ($pair) {
            if (isset($pair->unit_price) && $pair->unit_price !== null) {
                return [
                    'base_price'        => $base,
                    'fabric_surcharge'  => 0.0,
                    'color_surcharge'   => 0.0,
                    'unit_price'        => (float) $pair->unit_price,
                ];
            }
            if (isset($pair->surcharge_type, $pair->surcharge_value)) {
                $delta = $this->applySurcharge($base, $pair->surcharge_type, $pair->surcharge_value);
                return [
                    'base_price'        => $base,
                    'fabric_surcharge'  => 0.0,
                    'color_surcharge'   => $delta, // trattato come delta coppia
                    'unit_price'        => $base + $delta,
                ];
            }
        }

        // 2) Tessuto: prima pivot product_fabrics; se NULL ⇒ fallback a fabrics.*
        if ($fabricId) {
            /** @var Fabric|null $fabric */
            $fabric = Fabric::find($fabricId);
            if ($fabric) {
                $pf = $this->fabrics()->where('fabrics.id', $fabricId)->first();
                $type = $pf?->pivot?->surcharge_type ?? $fabric->surcharge_type ?? null;
                $val  = $pf?->pivot?->surcharge_value ?? $fabric->surcharge_value ?? null;
                $fabricS = $this->applySurcharge($base, $type, $val);
            }
        }

        // 3) Colore: prima pivot product_colors; se NULL ⇒ fallback a colors.*
        if ($colorId) {
            /** @var Color|null $color */
            $color = Color::find($colorId);
            if ($color) {
                $pc = $this->colors()->where('colors.id', $colorId)->first();
                $type = $pc?->pivot?->surcharge_type ?? $color->surcharge_type ?? null;
                $val  = $pc?->pivot?->surcharge_value ?? $color->surcharge_value ?? null;
                $colorS = $this->applySurcharge($base, $type, $val);
            }
        }

        return [
            'base_price'        => $base,
            'fabric_surcharge'  => $fabricS,
            'color_surcharge'   => $colorS,
            'unit_price'        => $base + $fabricS + $colorS,
        ];
    }

    /**
     * Applica un sovrapprezzo in base al tipo.
     * Tipi supportati: percent|percentage|%  oppure fixed|amount|€|eur (default: fisso).
     */
    protected function applySurcharge(float $base, ?string $type, $value): float
    {
        $v = is_null($value) ? 0.0 : (float) $value;
        return match ($type) {
            'percent', 'percentage', '%' => $base * ($v / 100),
            'fixed', 'amount', '€', 'eur', null, '' => $v,
            default => $v,
        };
    }
}