<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Component;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;     // <-- usato per le raw query

class ComponentApiController extends Controller
{
    /**
     * Autocomplete componenti (max 20 risultati).
     *
     * Accetta:
     *   ?q=term          ⇒ filtro per codice o descrizione
     *   ?supplier_id=id  ⇒ limita al listino di quel fornitore
     *
     * Ritorna JSON:
     *   id, code, description, unit_of_measure, (eventuale) last_cost
     */
    public function search(Request $request)
    {
        $term       = trim($request->get('q', ''));      // stringa di ricerca
        $supplierId = $request->get('supplier_id');      // opzionale

        /* --- SELECT base ------------------------------------------------ */
        $columns = [
            'components.id',
            'components.code',
            'components.description',
            'components.unit_of_measure',
        ];

        /* --- query ------------------------------------------------------ */
        $components = Component::query()
            /* filtro fornitore + prezzo ---------------------------------- */
            ->when($supplierId, function ($q) use ($supplierId, &$columns) {
                $q->join('component_supplier as cs', function ($join) use ($supplierId) {
                        $join->on('cs.component_id', '=', 'components.id')
                             ->where('cs.supplier_id', '=', $supplierId);
                    });
                $columns[] = 'cs.last_cost as last_cost';
            })
            ->where('components.is_active', true)

            /* filtro testuale case-insensitive --------------------------- */
            ->when($term !== '', function ($q) use ($term) {
                $needle = '%'.strtolower($term).'%';

                $q->where(function ($sub) use ($needle) {
                    $sub->whereRaw('LOWER(components.code)        LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(components.description) LIKE ?', [$needle]);
                });
            })

            ->orderBy('components.code')
            ->limit(20)
            ->get($columns);

        return response()->json($components);
    }
}
