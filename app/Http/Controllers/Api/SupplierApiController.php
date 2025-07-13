<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierApiController extends Controller
{
    /**
     * Restituisce un JSON di fornitori per l’autocomplete.
     *
     * @param  Request  $request  ->q string testo da cercare
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $q = trim(strtolower($request->get('q', '')));

        /* spazi o * in → %  – consente “SUPP*SPA” o “SUPP SPA” */
        $q = str_replace(['*'], '%', $q);
        $q = preg_replace('/\s+/', '%', $q);
        $needle = "%{$q}%";

        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->where(function ($sq) use ($needle) {
                $sq->whereRaw('LOWER(name)       LIKE ?', [$needle])
                ->orWhereRaw('LOWER(vat_number) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(tax_code)   LIKE ?', [$needle]);
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id','name','email','vat_number','address']);

        return response()->json($suppliers);
    }
}
