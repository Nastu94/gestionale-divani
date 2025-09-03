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
}