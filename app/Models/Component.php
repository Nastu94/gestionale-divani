<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modello Eloquent per la tabella 'components'.
 *
 * Rappresenta i singoli componenti utilizzati nella produzione di divani.
 */
class Component extends Model
{
    use SoftDeletes;

    /**
     * Gli attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'code',        // SKU interno
        'description', // Descrizione breve
        'material',    // Materiale principale
        'length',      // Lunghezza (cm)
        'width',       // Larghezza (cm)
        'height',      // Altezza (cm)
        'weight',      // Peso (kg)
        'unit',        // Unità di misura
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
}