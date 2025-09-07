<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class OrderComponentCheckController extends Controller
{
    /**
     * Verifica la copertura componenti di un OC (nuovo o in modifica).
     * NON crea prenotazioni né ordini fornitore: restituisce solo il risultato.
     *
     * POST /orders/check-components
     *
     * @bodyParam order_id        int   ID dell’OC in modifica (facoltativo)
     * @bodyParam delivery_date   date  Data consegna richiesta (Y-m-d)
     * @bodyParam lines           array [{product_id, quantity}]
     */
    public function check(Request $request)
    {
        $data = $request->validate([
            'order_id'               => ['nullable','integer','exists:orders,id'],
            'delivery_date'          => ['required','date'],
            'lines'                  => ['required','array','min:1'],
            'lines.*.product_id'     => ['required','integer','exists:products,id'],
            'lines.*.quantity'       => ['required','numeric','min:0.01'],
            'lines.*.fabric_id'       => ['nullable', 'integer', 'exists:fabrics,id'],
            'lines.*.color_id'       => ['nullable', 'integer', 'exists:colors,id'],
        ]);

        /* 1️⃣ Costruisce le righe per InventoryService */
        $lines = collect($data['lines'])
                    ->map(fn ($l) => [
                        'product_id' => $l['product_id'],
                        'quantity'   => $l['quantity'],
                        'fabric_id'  => (int) ($l['fabric_id'] ?? null),
                        'color_id'   => (int) ($l['color_id']  ?? null),
                    ])
                    ->values()      // indice 0-based
                    ->all();

        /* 2️⃣ Verifica disponibilità (solo read) */
        $inv = InventoryService::forDelivery(
                    $data['delivery_date'],
                    $data['order_id'] ?? null       // esclude le prenotazioni dell’OC se in edit
               )->check($lines);

        /* 3️⃣ Risposta */
        return response()->json([
            'ok'       => $inv->ok,
            'shortage' => $inv->shortage,          // dettagli per la tabella frontend
        ]);
    }
}
