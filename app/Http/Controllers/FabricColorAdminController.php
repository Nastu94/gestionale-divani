<?php
// app/Http/Controllers/VariableCatalogController.php
// -----------------------------------------------------------------------------
// Controller "catalogo variabili": gestisce i dati necessari alla vista di
// amministrazione per:
//  - CRUD di Fabrics & Colors (in questa fase: solo visualizzazione liste)
//  - Mapping dei componenti TESSU ↔ (fabric_id, color_id)
//  - Matrice disponibilità SKU per policy STRICT
//
// In questa fase implementiamo SOLO il metodo index() che prepara:
//  - cataloghi (fabrics, colors) attivi
//  - elenco componenti categoria "TESSU" con filtri (mapped/unmapped/conflicts, fabric, color, active, ricerca)
//  - conteggi (totali, mappati, non mappati, conflitti)
//  - mappa matrice fabric×color → component (per la griglia laterale)
// PHP 8.4 / Laravel 12
// -----------------------------------------------------------------------------

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Component;
use App\Models\ComponentCategory;
use App\Models\Fabric;
use App\Models\Color;
use Illuminate\View\View;

class FabricColorAdminController extends Controller
{
    /**
     * Mostra la pagina "Variabili (Fabrics & Colors) → Mapping TESSU"
     * con filtri, contatori e matrice delle coppie disponibili.
     */
    public function index(Request $request): View
    {
        // 1) Recupero ID categoria TESSU (obbligatoria per il mapping)
        $tessuCategoryId = ComponentCategory::query()
            ->where('code', 'TESSU')
            ->value('id');

        // Se non esiste la categoria, mostriamo pagina vuota con avviso
        // (non lanciamo eccezioni: UX più amichevole)
        if (!$tessuCategoryId) {
            $fabrics = Fabric::where('active', true)->orderBy('name')->get();
            $colors  = Color::where('active', true)->orderBy('name')->get();

            return view('variables.index', [
                'filters'        => ['state' => 'all', 'fabric_id' => null, 'color_id' => null, 'q' => '', 'active' => 'all'],
                'fabrics'        => $fabrics,
                'colors'         => $colors,
                'components'     => collect(),     // nessun componente TESSU
                'stats'          => ['total' => 0, 'mapped' => 0, 'unmapped' => 0, 'conflicts' => 0],
                'duplicates'     => [],            // nessuna coppia duplicata
                'matrix'         => [],            // matrice vuota
                'tessuMissing'   => true,          // flag per mostrare banner in vista
            ]);
        }

        // 2) Cataloghi attivi per select e matrice
        $fabrics = Fabric::where('active', true)->orderBy('name')->get();
        $colors  = Color::where('active', true)->orderBy('name')->get();

        // 3) Filtri GET (valori ammessi "state": all|mapped|unmapped|conflicts ; "active": all|1|0)
        $state    = in_array($request->query('state'), ['mapped','unmapped','conflicts','all'], true)
                  ? $request->query('state')
                  : 'all';

        $active   = in_array($request->query('active'), ['all','1','0'], true)
                  ? $request->query('active')
                  : 'all';

        $fabricId = $request->integer('fabric_id') ?: null;
        $colorId  = $request->integer('color_id')  ?: null;
        $q        = trim((string) $request->query('q', ''));

        // 4) Precomputo dei "duplicati" (coppie fabric_id+color_id assegnate a più componenti)
        $duplicatePairs = Component::query()
            ->select(['fabric_id', 'color_id', DB::raw('COUNT(*) as c')])
            ->where('category_id', $tessuCategoryId)
            ->whereNotNull('fabric_id')
            ->whereNotNull('color_id')
            ->groupBy('fabric_id', 'color_id')
            ->having('c', '>', 1)
            ->get()
            ->map(fn($r) => [$r->fabric_id, $r->color_id])
            ->values()
            ->all();

        // Utility per riconoscere "questa riga fa parte di un conflitto?"
        $isDuplicate = function (?int $fid, ?int $cid) use ($duplicatePairs): bool {
            if (!$fid || !$cid) return false;
            foreach ($duplicatePairs as [$df, $dc]) {
                if ($df === $fid && $dc === $cid) return true;
            }
            return false;
        };

        // 5) Query base componenti TESSU + applicazione filtri
        $componentsQuery = Component::query()
            ->where('category_id', $tessuCategoryId);

        // active filter
        if ($active !== 'all') {
            $componentsQuery->where('is_active', $active === '1');
        }

        // state filter
        if ($state === 'mapped') {
            $componentsQuery->whereNotNull('fabric_id')->whereNotNull('color_id');
        } elseif ($state === 'unmapped') {
            $componentsQuery->where(function ($q) {
                $q->whereNull('fabric_id')->orWhereNull('color_id');
            });
        } elseif ($state === 'conflicts') {
            // Se non ci sono duplicati, nessun risultato
            if (empty($duplicatePairs)) {
                $componentsQuery->whereRaw('1=0');
            } else {
                // Costruiamo OR multipli su coppie duplicate
                $componentsQuery->where(function ($q) use ($duplicatePairs) {
                    foreach ($duplicatePairs as [$fid, $cid]) {
                        $q->orWhere(function ($qq) use ($fid, $cid) {
                            $qq->where('fabric_id', $fid)->where('color_id', $cid);
                        });
                    }
                });
            }
        }

        // filtro per singoli fabric/color
        if ($fabricId) $componentsQuery->where('fabric_id', $fabricId);
        if ($colorId)  $componentsQuery->where('color_id',  $colorId);

        // ricerca testo su code/description
        if ($q !== '') {
            $componentsQuery->where(function ($qq) use ($q) {
                $qq->where('code', 'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // Ordinamento e paginazione (append per mantenere filtri nei link)
        $components = $componentsQuery
            ->orderBy('code')
            ->paginate(25)
            ->appends([
                'state'     => $state,
                'active'    => $active,
                'fabric_id' => $fabricId,
                'color_id'  => $colorId,
                'q'         => $q,
            ]);

        // 6) Statistiche globali (su tutti i TESSU, non solo sulla pagina)
        $baseStatsQuery = Component::query()->where('category_id', $tessuCategoryId);
        $total          = (clone $baseStatsQuery)->count();
        $mapped         = (clone $baseStatsQuery)->whereNotNull('fabric_id')->whereNotNull('color_id')->count();
        $unmapped       = $total - $mapped;
        $conflictsCount = Component::query()
            ->where('category_id', $tessuCategoryId)
            ->whereNotNull('fabric_id')
            ->whereNotNull('color_id')
            ->where(function ($q) use ($duplicatePairs) {
                if (empty($duplicatePairs)) {
                    $q->whereRaw('1=0');
                } else {
                    foreach ($duplicatePairs as [$fid, $cid]) {
                        $q->orWhere(function ($qq) use ($fid, $cid) {
                            $qq->where('fabric_id', $fid)->where('color_id', $cid);
                        });
                    }
                }
            })
            ->count();

        $stats = [
            'total'     => $total,
            'mapped'    => $mapped,
            'unmapped'  => $unmapped,
            'conflicts' => $conflictsCount,
        ];

        // 7) Matrice fabric×color → component (per la griglia laterale/riassunto)
        $allTessu = Component::query()
            ->where('category_id', $tessuCategoryId)
            ->whereNotNull('fabric_id')
            ->whereNotNull('color_id')
            ->get(['id','code','fabric_id','color_id','is_active']);

        $matrix = []; // [fabric_id][color_id] = ['id'=>..,'code'=>..,'is_active'=>..]
        foreach ($allTessu as $c) {
            $matrix[$c->fabric_id][$c->color_id] = [
                'id'        => $c->id,
                'code'      => $c->code,
                'is_active' => (bool) $c->is_active,
            ];
        }

        // 8) Ritorno alla vista
        return view('pages.variables.index', [
            'filters'      => [
                'state'     => $state,
                'active'    => $active,
                'fabric_id' => $fabricId,
                'color_id'  => $colorId,
                'q'         => $q,
            ],
            'fabrics'      => $fabrics,
            'colors'       => $colors,
            'components'   => $components,
            'stats'        => $stats,
            'duplicates'   => $duplicatePairs, // array di [fabric_id,color_id] duplicati
            'matrix'       => $matrix,         // coppie presenti → component
            'tessuMissing' => false,           // per banner informativo
            'isDuplicate'  => $isDuplicate,    // callable usato nella vista per evidenziare conflitti
        ]);
    }
}
