<?php
/**
 * Model: ProductReturn (testata reso)
 *
 * Rappresenta un reso registrato nel sistema. Può essere "solo amministrativo"
 * (nessuna riga con restock=true) oppure "in magazzino" (almeno una riga con restock=true).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReturn extends Model
{
    use HasFactory;

    /** @var array<int, string> Mass assignment: consentiamo i campi della testata. */
    protected $fillable = [
        'number',
        'customer_id',
        'order_id',
        'return_date',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> Cast automatici per comodo accesso typed. */
    protected $casts = [
        'return_date' => 'date',
    ];

    /** Cliente associato al reso. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Eventuale ordine cliente di riferimento. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Utente che ha creato la testata reso. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Righe del reso. */
    public function lines(): HasMany
    {
        return $this->hasMany(ProductReturnLine::class);
    }

    /**
     * Stato derivato (solo amministrativo / in magazzino).
     * - "solo amministrativo": nessuna riga con restock=true
     * - "in magazzino": almeno una riga con restock=true
     */
    protected function status(): Attribute
    {
        return Attribute::get(function (): string {
            $hasRestock = $this->relationLoaded('lines')
                ? $this->lines->contains(fn ($l) => (bool) $l->restock)
                : $this->lines()->where('restock', true)->exists();

            return $hasRestock ? 'in magazzino' : 'solo amministrativo';
        });
    }
}
