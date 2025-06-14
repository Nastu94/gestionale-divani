<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modello Eloquent per la tabella 'suppliers'.
 *
 * Contiene l'anagrafica dei fornitori.
 */
class Supplier extends Model
{
    use SoftDeletes;

    /**
     * Attributi assegnabili in massa.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',       // Ragione sociale
        'vat_number', // Partita IVA
        'tax_code',   // Codice fiscale
        'email',      // Email di contatto
        'phone',      // Telefono principale
    ];

    /**
     * Relazione molti a molti con Component.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function components()
    {
        return $this->belongsToMany(Component::class, 'component_supplier')
                    ->withTimestamps();
    }

    /**
     * Relazione uno a molti con Order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}