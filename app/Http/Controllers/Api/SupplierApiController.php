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
        $q = trim($request->get('q', ''));

        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->when($q, fn ($qry) => $qry->where(function ($sq) use ($q) {
                $sq->where('name',       'like', "%{$q}%")
                   ->orWhere('vat_number','like', "%{$q}%")
                   ->orWhere('tax_code',  'like', "%{$q}%");
            }))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email','vat_number','address']);

        return response()->json($suppliers);
    }
}
