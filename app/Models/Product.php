<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modello per la tabella 'products'.
 *
 * Rappresenta i modelli finiti di divano (SKU commerciali).
 */
class Product extends Model
{
    use SoftDeletes;

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
}