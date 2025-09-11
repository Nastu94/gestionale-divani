<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class OrderPricingController extends Controller
{
    /**
     * Calcola il prezzo unitario lordo (base + tessuto/colore) e POI applica gli sconti.
     * Gli sconti NON dipendono dai metri: si applicano sul prezzo finale del prodotto.
     */
    public function quoteLine(Request $r)
    {
        $r->validate([
            'product_id'  => 'required|integer',
            'qty'         => 'required|integer|min:1',
            'fabric_id'   => 'nullable|integer',
            'color_id'    => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            // token sconti: "N%" oppure "N" (euro)
            'discounts'   => 'sometimes|array',
            'discounts.*' => ['string','regex:/^\d+(\.\d+)?%?$/'],
        ]);

        $product    = Product::findOrFail($r->integer('product_id'));
        $qty        = max(1, $r->integer('qty'));
        $fabricId   = $r->filled('fabric_id')   ? (int) $r->fabric_id   : null;
        $colorId    = $r->filled('color_id')    ? (int) $r->color_id    : null;
        $customerId = $r->filled('customer_id') ? (int) $r->customer_id : null;

        // 1) LORDO unitario già comprensivo di variabili (tessuto/colore)
        $q         = $product->unitPriceFor($fabricId, $colorId, $customerId);
        $unitGross = (float) ($q['unit_price'] ?? 0.0);

        // 2) SCONTI: applicazione sequenziale sul lordo (nessun uso di metri qui)
        $tokens                 = $r->array('discounts') ?? [];
        [$unitNet, $discValue]  = $this->applyDiscountTokens($unitGross, $tokens);

        return response()->json([
            // compat: breakdown lordo (come già restituito)
            ...$q,
            'subtotal'              => $unitGross * $qty,

            // netto post-sconti (nuove chiavi)
            'discounts'             => $tokens,
            'discount_total_unit'   => $discValue,
            'discounted_unit_price' => max(0, $unitNet),
            'discounted_subtotal'   => max(0, $unitNet) * $qty,
        ]);
    }

    /**
     * Applica token tipo "10%" o "25" (euro) in sequenza al prezzo lordo.
     * Nessuna moltiplicazione per metri: gli sconti sono sul prezzo prodotto.
     *
     * @return array{0: float, 1: float} [unit_net, discount_value]
     */
    private function applyDiscountTokens(float $unitGross, array $tokens): array
    {
        $price = $unitGross;

        foreach ($tokens as $tok) {
            if (!is_string($tok) || $tok === '') continue;
            $tok = trim($tok);

            if (str_ends_with($tok, '%')) {
                $p = (float) substr($tok, 0, -1);
                $price = $price * max(0.0, (100.0 - $p)) / 100.0; // percentuale sul prezzo corrente
            } else {
                $f = (float) $tok;
                $price = $price - max(0.0, $f); // sconto fisso in €
            }
        }

        $unitNet = max(0.0, round($price, 2));
        $discVal = round($unitGross - $unitNet, 2);

        return [$unitNet, $discVal];
    }
}
