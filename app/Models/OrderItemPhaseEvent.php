<?php

namespace App\Models;

use App\Enums\ProductionPhase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * OrderItemPhaseEvent
 *
 * @property int                 $id
 * @property int                 $order_item_id
 * @property ProductionPhase     $from_phase
 * @property ProductionPhase     $to_phase
 * @property float               $quantity
 * @property int                 $changed_by
 * @property bool                $is_rollback
 * @property string|null         $reason
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
class OrderItemPhaseEvent extends Model
{
    use LogsActivity;

    /** @var string */
    protected $table = 'order_item_phase_events';

    /** @var array<int,string> */
    protected $fillable = [
        'order_item_id',
        'from_phase',
        'to_phase',
        'quantity',
        'changed_by',
        'is_rollback',
        'reason',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'from_phase' => ProductionPhase::class,
        'to_phase'   => ProductionPhase::class,
        'is_rollback'=> 'boolean',
        'quantity'   => 'float',
    ];

    // ─────────────────────────────────────── Relationships

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ─────────────────────────────────────── Logging
    /**
     * Nome del log per distinguere le attività di questo modello.
     */
    protected static $logName = 'order_item_phase_event';

    /**
     * Attributi che devono essere registrati nel log delle attività.
     */
    protected static $logAttributes = [
        'order_item_id',
        'from_phase',
        'to_phase',
        'quantity',
        'changed_by',
        'is_rollback',
        'reason',
    ];
    
    /**
     * Configura le opzioni di logging per questo modello.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->useLogName('order_item_phase_event'); // nome del log per distinguere 
    }
}
