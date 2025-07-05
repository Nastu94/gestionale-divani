<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use Illuminate\Http\Request;

class ComponentApiController extends Controller
{
    /**
     * Autocomplete componenti (max 10 risultati).
     * Se è presente supplier_id, limita ai componenti forniti da quel
     * fornitore e restituisce anche il prezzo di listino (last_cost).
     */
    public function search(Request $request)
    {
        $q          = trim($request->get('q', ''));
        $supplierId = $request->get('supplier_id');   // opzionale

        /* ---------- SELECT base ---------- */
        $columns = [
            'components.id',
            'components.code',
            'components.description',
            'components.unit_of_measure as unit',
        ];

        /* ---------- Query ---------- */
        $components = Component::query()
            ->when($supplierId, function ($qry) use ($supplierId, &$columns) {
                // Join pivot solo se voglio filtrare per fornitore
                $qry->join('component_supplier as cs', function ($join) use ($supplierId) {
                        $join->on('cs.component_id', '=', 'components.id')
                             ->where('cs.supplier_id', '=', $supplierId);
                    });
                // Aggiungo il prezzo alla SELECT con alias 'price'
                $columns[] = 'cs.last_cost as price';
            })
            ->where('components.is_active', true)
            ->when($q, fn ($qry) => $qry->where(function ($sq) use ($q) {
                $sq->where('components.code',        'like', "%{$q}%")
                   ->orWhere('components.description','like', "%{$q}%");
            }))
            ->orderBy('components.code')
            ->limit(20)
            ->get($columns);

        return response()->json($components);
    }
}
