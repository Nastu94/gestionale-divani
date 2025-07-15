<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderComponentCheckController extends Controller
{
    /**
     * Valida le righe ordine, controlla la disponibilità e – se
     * l’utente ha permesso orders.supplier.create – genera/merge
     * gli ordini fornitore necessari.
     *
     * POST /orders/check-components
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        $data = $request->validate([
            'delivery_date'          => ['required', 'date'],
            'lines'                  => ['required', 'array', 'min:1'],
            'lines.*.product_id'     => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity'       => ['required', 'numeric', 'min:0.01'],
        ]);

        /* 1. Verifica disponibilità */
        $inventory = InventoryService::forDelivery($data['delivery_date'])
                        ->check($data['lines']);

        /* 2. Se mancano componenti e l’utente può creare OF → genera PO */
        $poIds = [];
        if (! $inventory->ok && Auth::user()->can('orders.supplier.create')) {
            $pos   = ProcurementService::fromShortage(
                $inventory->shortage,
                $data['delivery_date'],
                null           // nessun OC ancora: sarà null
            );
            $poIds = $pos->pluck('id')->all();
        }

        /* 3. Risposta */
        return response()->json([
            'ok'       => $inventory->ok,
            'shortage' => $inventory->shortage,
            'po_ids'   => $poIds,             // [] se nessun PO generato
        ]);
    }
}
