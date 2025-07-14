<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * API di ricerca rapida prodotti per il modale Ordini Cliente.
 *
 * Ritorna al massimo 20 righe con:
 *   id, sku, name, price
 *
 * Parametri:
 *   • q              – termine di ricerca (obbligatorio ≥2 char)
 *   • include_price  – bool facoltativo; se =false nasconde price
 *
 * @see resources/js/customer-order-create-modal.blade.php
 */
class ProductsApiController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        /* ---------- validazione input minima ---------- */
        $request->validate([
            'q'             => 'required|string|min:2',
            'include_price' => 'nullable|boolean',
        ]);

        $term = strtolower(trim($request->get('q')));
        $term = str_replace('*', '%', $term);       // supporta wildcard “*”
        $term = preg_replace('/\s+/', '%', $term);  // spazi → %
        $like = "%{$term}%";

        /* ---------- query ---------- */
        $products = Product::query()
            ->select(['id', 'sku', 'name', 'price'])          // campi presenti nel model Product :contentReference[oaicite:1]{index=1}
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(sku)  LIKE ?', [$like])
                  ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
            })
            ->orderBy('sku')
            ->limit(20)
            ->get();

        /* ---------- opzionale: nasconde price ---------- */
        if (!$request->boolean('include_price', true)) {
            $products->makeHidden('price');
        }

        return response()->json($products);
    }
}
