<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Support\Pricing\CustomerPriceResolver;
use Illuminate\Support\Carbon;

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
    // inietta il resolver
    public function __construct(private CustomerPriceResolver $priceResolver) {}

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

        // Parametri opzionali per il resolver
        $customerId = $request->integer('customer_id') ?: null;
        $dateParam  = $request->input('date');
        $date       = $dateParam ? Carbon::parse($dateParam) : null;

        // Arricchimento opzionale: aggiungo i campi calcolati
        if ($customerId !== null || $date !== null) {
            $products->transform(function ($p) use ($customerId, $date) {
                $r = app(CustomerPriceResolver::class)->resolve((int) $p->id, $customerId, $date);

                // Campi extra: rimangono visibili anche se nascondo 'price'
                $p->effective_price = $r['price']      ?? null;
                $p->price_source    = $r['source']     ?? null; // 'customer' | 'base'
                $p->valid_from      = $r['valid_from'] ?? null;
                $p->valid_to        = $r['valid_to']   ?? null;

                return $p;
            });
        }

        /* ---------- opzionale: nasconde price ---------- */
        if (!$request->boolean('include_price', true)) {
            $products->makeHidden('price');
        }

        return response()->json($products);
    }
}
