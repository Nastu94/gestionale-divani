<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API Autocomplete clienti.
 *
 * Ritorna id, ragione sociale, P.IVA, C.F. e primo indirizzo di spedizione.
 * Endpoint:  GET /customers/search?q=...
 *
 * @author [...]
 */
class CustomersApiController extends Controller
{
    /**
     * Ricerca clienti attivi con filtro testuale.
     *
     * @param  Request  $request  q=termine libero (* e spazi → wildcard)
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $q = trim(strtolower($request->get('q', '')));

        /* normalizza il pattern  (es. “DIVANI*SPA” → “%divani%spa%”) */
        $q       = str_replace(['*'], '%', $q);
        $q       = preg_replace('/\s+/', '%', $q);
        $needle  = "%{$q}%";

        /* -----------------------------------------------------------------
         |  Query: clienti attivi con primo indirizzo di spedizione
         |----------------------------------------------------------------- */
        $customers = Customer::query()
            ->select([
                'customers.id',
                'customers.company',
                'customers.email',
                'customers.vat_number',
                'customers.tax_code',
                /* sub-query: 1° indirizzo type = "shipping" */
                DB::raw("(
                    SELECT CONCAT_WS(', ',
                       ca.address,
                       ca.city,
                       ca.postal_code,
                       ca.country
                    )
                    FROM customer_addresses AS ca
                    WHERE ca.customer_id = customers.id
                      AND ca.type = 'shipping'
                    ORDER BY ca.id
                    LIMIT 1
                 ) AS shipping_address"),
            ])
            ->where('customers.is_active', true)
            ->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(customers.company)    LIKE ?', [$needle])
                  ->orWhereRaw('LOWER(customers.vat_number) LIKE ?', [$needle])
                  ->orWhereRaw('LOWER(customers.tax_code)   LIKE ?', [$needle]);
            })
            ->orderBy('customers.company')
            ->limit(20)
            ->get();

        return response()->json($customers);
    }
}
