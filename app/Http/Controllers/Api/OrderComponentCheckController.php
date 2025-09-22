<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Services\ReturnedProductReservationService;
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
     * @bodyParam lines           array [{product_id, quantity, fabric_id?, color_id?}]
     */
    public function check(Request $request)
    {
        $data = $request->validate([
            'order_id'              => ['nullable','integer','exists:orders,id'],
            'delivery_date'         => ['required','date'],
            'lines'                 => ['required','array','min:1'],
            'lines.*.product_id'    => ['required','integer','exists:products,id'],
            'lines.*.quantity'      => ['required','numeric','min:0.01'],
            'lines.*.fabric_id'     => ['nullable','integer','exists:fabrics,id'],
            'lines.*.color_id'      => ['nullable','integer','exists:colors,id'],
        ]);

        // 1) Normalizza righe (tipi e null-safety)
        $lines = collect($data['lines'])->map(function ($l) {
            return [
                'product_id' => (int) $l['product_id'],
                'quantity'   => (float) $l['quantity'],
                'fabric_id'  => array_key_exists('fabric_id',$l) ? $l['fabric_id'] : null,
                'color_id'   => array_key_exists('color_id',$l)  ? $l['color_id']  : null,
            ];
        })->values();

        // 2) COPERTURA DA RESI (dry-run) – calcola quanto potrei coprire per chiave prodotto/FC
        $makeKey = fn($pid,$fid,$cid) => sprintf('%d:%d:%d', $pid, $fid ?? 0, $cid ?? 0);

        /** @var ReturnedProductReservationService $returnsSvc */
        $returnsSvc = app(ReturnedProductReservationService::class);

        $availableCoverByKey = [];   // mappa: key => qty copribile
        foreach ($lines as $l) {
            $covered = (float) $returnsSvc->dryRunCover(
                productId: (int)   $l['product_id'],
                quantity:  (float) $l['quantity'],
                fabricId:  $l['fabric_id'] !== null ? (int)$l['fabric_id'] : null,
                colorId:   $l['color_id']  !== null ? (int)$l['color_id']  : null,
                excludeOrderId: $data['order_id'] ?? null
            );

            if ($covered > 0) {
                $k = $makeKey($l['product_id'], $l['fabric_id'], $l['color_id']);
                $availableCoverByKey[$k] = ($availableCoverByKey[$k] ?? 0) + $covered;
            }
        }

        // 3) Applica la copertura dei resi e costruisci:
        //    - $usedLines per l'InventoryService
        //    - $returnsCoverageApplied per il FE (quanto è stato coperto da resi per ciascuna riga)
        $returnsCoverageApplied = [];
        $usedLines = $lines->map(function ($l) use (&$availableCoverByKey, $makeKey, &$returnsCoverageApplied) {

            $pid = $l['product_id'];
            $fid = $l['fabric_id'];
            $cid = $l['color_id'];
            $qty = (float) $l['quantity'];

            $k = $makeKey($pid, $fid, $cid);
            $canCover = (float) ($availableCoverByKey[$k] ?? 0);

            if ($canCover > 0) {
                $take = min($qty, $canCover);
                // registra copertura applicata per questa riga
                $returnsCoverageApplied[] = [
                    'product_id' => $pid,
                    'fabric_id'  => $fid,
                    'color_id'   => $cid,
                    'reserved'   => $take,
                ];
                // consuma la mappa di copertura disponibile
                $availableCoverByKey[$k] = max($canCover - $take, 0);
                $qty -= $take;
            }

            // se la riga è stata coperta interamente, non entra nel fabbisogno componenti
            if ($qty <= 1e-6) return null;

            return [
                'product_id' => $pid,
                'quantity'   => $qty,
                'fabric_id'  => $fid,
                'color_id'   => $cid,
            ];
        })
        ->filter()
        ->values()
        ->all();

        // 4) Verifica disponibilità componenti (escludendo le prenotazioni dell’OC, se in edit)
        $inv = InventoryService::forDelivery(
            $data['delivery_date'],
            $data['order_id'] ?? null
        )->check($usedLines);

        // 5) Risposta: shortage (componenti ancora mancanti) + coperture da reso applicate
        return response()->json([
            'ok'               => $inv->ok,
            'shortage'         => $inv->shortage,
            // Copertura effettivamente applicata per riga (quella utile al FE)
            'returns_coverage' => $returnsCoverageApplied,
        ]);
    }
}
