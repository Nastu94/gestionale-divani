<?php
// app/Http/Controllers/Api/CustomersApiController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OccasionalCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class CustomersApiController extends Controller
{
    /**
     * Autocomplete clienti.
     *
     * Parametri:
     *  - q                   (string, min 2)  termine di ricerca
     *  - limit               (int, default 20, max 50)
     *  - include_occasional  (bool, default false) → se true, unisce
     *                         anche i risultati da occasional_customers
     */
    public function search(Request $request): JsonResponse
    {
        // ── validazione input
        $request->validate([
            'q'                  => 'required|string|min:2',
            'limit'              => 'nullable|integer|min:1|max:50',
            'include_occasional' => 'nullable|boolean',
        ]);

        $withOccasional = $request->boolean('include_occasional', false);
        $limit  = (int) min(max((int) $request->input('limit', 20), 1), 50);
        $term   = strtolower(trim((string) $request->input('q')));
        $like   = '%' . str_replace(['*',' '], ['%','%'], $term) . '%';

        // 1) Clienti standard → mappo SUBITO in array (Support\Collection)
        $regular = Customer::query()
            ->select(['id','company','email'])
            ->where('is_active', true)
            ->whereRaw('LOWER(COALESCE(company,"")) LIKE ?', [$like])
            ->orderBy('company')
            ->limit($limit)
            ->get()
            ->map(function ($c) {
                // indirizzo di spedizione (usa accessor se lo hai, altrimenti componi a mano)
                $shipping = optional($c->shippingAddress)->full_address
                        ?? optional($c->shippingAddress)->address
                        ?? null;

                return [
                    'id'               => (int) $c->id,
                    'company'          => $c->company,
                    'email'            => $c->email,
                    'shipping_address' => $shipping,
                    'source'           => 'customer',
                ];
            })
            ->values(); 

        // 2) Clienti occasionali (solo se richiesto) → anche qui array
        $occasional = collect();
        if ($withOccasional) {
            $occasional = OccasionalCustomer::query()
                ->select(['id','company','email','address','postal_code','city','province','country'])
                ->whereRaw('LOWER(COALESCE(company,"")) LIKE ?', [$like])
                ->orderBy('company')
                ->limit($limit)
                ->get()
                ->map(function ($o) {
                    $addr = collect([
                        $o->address,
                        trim(($o->postal_code ? $o->postal_code.' ' : '').($o->city ?? '')),
                        $o->province,
                        $o->country,
                    ])->filter()->implode(', ');

                    return [
                        'id'               => (int) $o->id,
                        'company'          => $o->company,
                        'email'            => $o->email,
                        'shipping_address' => $addr ?: null,
                        'source'           => 'occasional',
                    ];
                })
                ->values();
        }

        // 3) Unione: usa concat (non merge) per evitare la logica sui key dei Model
        $all = $regular
            ->concat($occasional)
            ->sortBy('company', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->take($limit);

        return response()->json($all);
    }
}
