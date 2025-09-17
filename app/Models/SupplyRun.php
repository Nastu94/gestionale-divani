<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
* Modello Eloquent per i run di riconciliazione settimanale.
*
* Nota: è un semplice contenitore di telemetria; non intacca i service esistenti.
*/
class SupplyRun extends Model
{
    use HasFactory;

    /** @var array<int, string> $fillable Campi assegnabili in massa. */
    protected $fillable = [
        'window_start', 'window_end', 'week_label',
        'started_at', 'finished_at', 'duration_ms',
        'orders_scanned', 'orders_skipped_fully_covered', 'orders_touched',
        'stock_reservation_lines', 'stock_reserved_qty',
        'po_reservation_lines', 'po_reserved_qty',
        'components_in_shortfall', 'shortfall_total_qty',
        'purchase_orders_created', 'created_po_ids',
        'notes', 'result', 'error_context', 'trace_id', 'meta',
    ];

    /** @var array<string, string> $casts Cast automatici per tipi complessi. */
    protected $casts = [
        'window_start' => 'date',
        'window_end' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'stock_reserved_qty' => 'decimal:3',
        'po_reserved_qty' => 'decimal:3',
        'shortfall_total_qty'=> 'decimal:3',
        'created_po_ids' => 'array',
        'notes' => 'array',
        'error_context' => 'array',
        'meta' => 'array',
    ];
}