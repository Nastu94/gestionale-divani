<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Component;
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