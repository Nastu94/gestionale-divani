<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Supplier;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\Alert;
use App\Models\ComponentCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\belongsToMany;

/**
 * Modello Eloquent per la tabella 'components'.
 *
 * Rappresenta i singoli componenti utilizzati nella produzione di divani.
 */
class Component extends Model
{
    use SoftDeletes;
    use LogsActivity;

    /**
     * Attributi che devono essere registrati nel log delle attività.
     *
     * @var array<string>
     */
    protected static $logAttributes = [
        'category_id',
        'code',
        'description',
        'material',
        'length',
        'width',
        'height',
        'weight',
    ];

    protected static $logName = 'component';

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
            ->useLogName('component'); // nome del log per distinguere
    }

    /**
     * Gli attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'category_id', // ID della categoria (FK)
        'code',        // SKU interno
        'description', // Descrizione breve
        'material',    // Materiale principale
        'length',      // Lunghezza (cm)
        'width',       // Larghezza (cm)
        'height',      // Altezza (cm)
        'weight',      // Peso (kg)
        'unit_of_measure',        // Unità di misura
        'is_active',   // Flag attivo/inattivo
    ];

    /**
     * Relazione molti a molti con Supplier.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'component_supplier')
                    ->withTimestamps();
    }

    /**
     * Relazione uno a molti con StockLevel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Relazione uno a molti con StockMovement.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Relazione molti a molti con Product tramite product_components.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_components')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    /**
     * Relazione uno a molti con Alert.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Relazione uno a molti con ComponentCategory.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(ComponentCategory::class, 'category_id');
    }
}