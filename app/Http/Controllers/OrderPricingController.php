<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class OrderPricingController extends Controller
{
    /**
     * Calcola il prezzo unitario e il subtotale per una riga di ordine.
     *
     * @param  \Illuminate\Http\Request  $r
     * @return \Illuminate\Http\JsonResponse
     */
    public function quoteLine(Request $r)
    {
        $r->validate([
            'product_id'  => 'required|integer',
            'qty'         => 'required|integer|min:1',
            'fabric_id'   => 'nullable|integer',
            'color_id'    => 'nullable|integer',
            'customer_id' => 'nullable|integer', // se vuoi: |exists:customers,id
        ]);

        $product    = Product::findOrFail($r->integer('product_id'));
        $qty        = max(1, $r->integer('qty'));
        $fabricId   = $r->filled('fabric_id')  ? (int) $r->fabric_id  : null;
        $colorId    = $r->filled('color_id')   ? (int) $r->color_id   : null;
        $customerId = $r->filled('customer_id')? (int) $r->customer_id: null;

        $q = $product->unitPriceFor($fabricId, $colorId, $customerId);

        return response()->json([
            ...$q,
            'subtotal' => $q['unit_price'] * $qty,
        ]);
    }
}