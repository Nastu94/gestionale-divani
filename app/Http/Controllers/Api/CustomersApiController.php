<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * API Autocomplete clienti.
 *
 * Ritorna id, ragione sociale, P.IVA, C.F. e primo indirizzo di spedizione.
 * Endpoint:  GET /customers/search?q=...
 *
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
        $raw = strtolower(trim($request->get('q', '')));
        $raw = str_replace('*', '%', $raw);
        $raw = preg_replace('/\s+/', '%', $raw);
        $needle = "%{$raw}%";

        $customers = Customer::query()
            // 1) join sugli indirizzi di tipo 'shipping'
            ->join('customer_addresses as ca', function($join) {
                $join->on('customers.id', '=', 'ca.customer_id')
                    ->where('ca.type', 'shipping');
            })
            // 2) seleziono i campi del customer + concat_ws per shipping_address
            ->select([
                'customers.id',
                'customers.company',
                'customers.email',
                'customers.vat_number',
                'customers.tax_code',
                DB::raw("CONCAT_WS(', ', ca.address, ca.city, ca.postal_code, ca.country) AS shipping_address"),
            ])
            // 3) solo attivi
            ->where('customers.is_active', true)
            // 4) filtro su company / vat / tax_code
            ->where(function($q) use($needle) {
                $q->whereRaw('LOWER(customers.company)    LIKE ?', [$needle])
                ->orWhereRaw('LOWER(customers.vat_number) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(customers.tax_code)   LIKE ?', [$needle]);
            })
            // 5) ordino e limito
            ->orderBy('customers.company')
            ->limit(20)
            ->get();

        Log::debug('CustomersApiController@search', [
            'needle'    => $needle,
            'customer'  => $customers,
        ]);

        return response()->json($customers);
    }
}
