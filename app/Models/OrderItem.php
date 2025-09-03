<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\OrderProductVariable;
use App\Enums\ProductionPhase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; 

/**
 * Modello per la tabella 'order_items'.
 *
 * Righe di dettaglio per ogni ordine.
 */
class OrderItem extends Model
{
    /**
     * Attributi assegnabili in massa.
     */
    protected $fillable = [
        'order_id',
        'product_id',   // ID del prodotto associato, se presente ordine cliente
        'component_id', // ID del componente associato, se presente ordine fornitore
        'quantity',   // Quantità ordinata
        'unit_price', // Prezzo unitario
        'generated_by_order_customer_id', // ID dell'ordine cliente che ha generato questa riga, se applicabile
        'current_phase', // Fase di produzione corrente
        'qty_completed', // Quantità completata nella fase corrente
        'phase_updated_at' // Timestamp dell'ultimo aggiornamento della fase
    ];

    /**
     * Cast degli attributi.
     * 
     * @var array<string,string> 
     *
     */
    protected $casts = [
        'current_phase' => ProductionPhase::class,
        'qty_completed' => 'float',
        'phase_updated_at' => 'datetime',
    ];

    /**
     * Ordine di appartenenza.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Componente associato alla riga ordine.
     */
    public function component()
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Prodotto associato alla riga ordine.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    
    /**
     * Short-fall (quantità non consegnata) associato a questa riga.
     * Ritorna null se la riga è stata interamente evasa.
     */
    public function shortfall()
    {
        return $this->hasOne(OrderItemShortfall::class);
    }

    /**
     * Relazione con la riga riservata per l'ordine cliente.
     * Ritorna la riga riservata se esiste, altrimenti null.
     */
    public function poReservations()
    {
        return $this->hasMany(PoReservation::class);
    }

    /**
     * Eventi di fase associati a questo elemento dell'ordine.
     */
    public function phaseEvents(): HasMany
    {
        return $this->hasMany(OrderItemPhaseEvent::class);
    }

    /**
     * OC che ha originato questa riga PO (nullable)
     */
    public function generatedByOc(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'generated_by_order_customer_id');
    }

    /**
     * Variabili scelte su questa riga d'ordine (tipicamente 1: FABRIC_MAIN).
     */
    public function variables(): HasMany
    {
        return $this->hasMany(OrderProductVariable::class);
    }
    
    /**
     * Scope per filtrare le righe che hanno quantità > 0 in una determinata fase.
     */
    public function scopeWithQtyInPhase($query, ProductionPhase $phase)
    {
        return $query->whereHas('phaseEvents', function ($q) use ($phase) {
            $q->selectRaw('SUM(CASE WHEN to_phase = ? THEN quantity END) - 
                           SUM(CASE WHEN from_phase = ? THEN quantity END) > 0', 
                           [$phase->value, $phase->value]);
        });
    }

    /**
     * Ritorna la quantità attualmente presente in una data fase.
     */
    public function quantityInPhase(ProductionPhase $phase): float
    {
        // qty entrate in ♦to_phase
        $in = $this->phaseEvents()
            ->where('to_phase',   $phase->value)
            ->sum('quantity');

        // qty uscite da ♦from_phase
        $out = $this->phaseEvents()
            ->where('from_phase', $phase->value)
            ->sum('quantity');

        // base = qty iniziale solo per la fase INSERTED (0)
        $base = $phase === ProductionPhase::INSERTED
            ? (float) $this->quantity
            : 0.0;

        return $base + $in - $out;         // residuo effettivo
    }
}