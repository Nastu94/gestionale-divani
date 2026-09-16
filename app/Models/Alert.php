<?php

namespace App\Models;

use App\Enums\ProductionPhase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Modello per la tabella 'alerts'.
 *
 * Gestisce notifiche e avvisi generici legati al magazzino.
 */
class Alert extends Model
{
    /**
     * Attributi assegnabili in massa.
     */
    protected $fillable = [
        'dedupe_key', // Chiave di deduplicazione
        'type',       // Tipo avviso
        'message',    // Testo avviso
        'payload',    // Dati aggiuntivi (JSON)
        'is_read',    // Stato lettura
        'triggered_at'// Data creazione
    ];

    /**
     * Cast degli attributi.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'payload' => 'array',
        'is_read' => 'boolean',
        'triggered_at' => 'datetime',
    ];

    /**
     * Mostra gli alert di consegna solo finché restano pezzi prima della Spedizione.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $alerts) {
            $alerts->whereNotIn('alerts.type', ['delivery_15_days', 'delivery_20_days'])
                ->orWhereExists(function (QueryBuilder $items) {
                    $items->selectRaw('1')
                        ->from('order_items')
                        ->join('v_order_item_phase_qty', 'v_order_item_phase_qty.order_item_id', '=', 'order_items.id')
                        ->whereColumn('order_items.order_id', 'alerts.payload->order_id')
                        ->where('v_order_item_phase_qty.phase', '<', ProductionPhase::SHIPPING->value)
                        ->where('v_order_item_phase_qty.qty_in_phase', '>', 0);
                });
        });
    }
}
