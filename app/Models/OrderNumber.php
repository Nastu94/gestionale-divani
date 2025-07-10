<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderNumber extends Model
{   
    /**
     * La tabella associata al modello.
     *
     * @var string
     */
    protected $table = 'order_numbers';

    /**
     * Gli attributi che possono essere assegnati in massa.
     *
     * @var array
     */
    protected $fillable = [
        'order_type', 
        'number',
    ];

    /**
     * Cast degli attributi.
     *
     * @var array
     */
    protected $casts = [
        'number' => 'integer',
    ];

    /**
     * Indica se il modello utilizza le colonne di timestamp.
     *
     * @var bool
     */
    public $timestamps  = true;

    /**
     * La relazione con il modello Order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order() { 
        return $this->hasOne(Order::class); 
    }

    /* ------------------------------------------------------------------
     |  reserve() – prenota il prossimo progressivo in maniera atomica
     |-------------------------------------------------------------------
     | @param  string  $type   es. 'supplier'
     | @return self            modello appena creato con id + number
     * ----------------------------------------------------------------- */
    public static function reserve(string $type = 'supplier'): self
    {
        return DB::transaction(function () use ($type) {

            // ultimo numero già usato per quel tipo
            $last = self::where('order_type', $type)
                    ->lockForUpdate()          // previene race-condition
                    ->max('number');           // null se primo ordine

            $next = ($last ?? 0) + 1;

            // crea e restituisce la nuova riga
            return self::create([
                'order_type' => $type,
                'number'     => $next,
            ]);
        });
    }
}
