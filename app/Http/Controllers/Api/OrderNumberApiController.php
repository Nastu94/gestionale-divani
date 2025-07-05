<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderNumberApiController extends Controller
{
    /**
     * Restituisce e incrementa in modo atomico il progressivo.
     *
     * URL es.: /order-number/reserve?type=supplier   → { "next": 58 }
     */
    public function reserve(): JsonResponse
    {
        $type = request()->input('type', 'supplier');

        $row = DB::transaction(function () use ($type) {

            // prendi l'ultimo numero già usato per quel tipo
            $last = OrderNumber::where('order_type', $type)
                    ->lockForUpdate()
                    ->max('number');               // null se primo ordine

            $next = ($last ?? 0) + 1;

            // crea la nuova riga che verrà poi referenziata da orders
            return OrderNumber::create([
                'order_type' => $type,
                'number'     => $next,
            ]);
        });

        return response()->json([
            'id'     => $row->id,
            'number' => $row->number,
            'type'   => $row->order_type,
        ]);
    }
}
