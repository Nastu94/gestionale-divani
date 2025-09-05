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
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use App\Models\Component;
use App\Models\ComponentCategory;
use App\Models\Fabric;
use App\Models\Color;

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

        // 2) Cataloghi attivi per select e matrice
        $fabrics = Fabric::where('active', true)->orderBy('name')->get();
        $colors  = Color::where('active', true)->orderBy('name')->get();

        // --- Alias (Fase A: da config + nomi ufficiali DB) -------------------
        [$fabricAliases, $colorAliases, $ambiguousColorTerms] = $this->buildAliases($fabrics, $colors);

        // Se non esiste la categoria, mostriamo pagina vuota con avviso
        // (non lanciamo eccezioni: UX più amichevole)
        if (!$tessuCategoryId) {
            return view('pages.variables.index', [
                'filters'        => ['state' => 'all', 'fabric_id' => null, 'color_id' => null, 'q' => '', 'active' => 'all'],
                'fabrics'        => $fabrics,
                'colors'         => $colors,
                'components'     => collect(),
                'stats'          => ['total' => 0, 'mapped' => 0, 'unmapped' => 0, 'conflicts' => 0],
                'duplicates'     => [],
                'matrix'         => [],
                'tessuMissing'   => true,
                'isDuplicate'    => fn() => false,
                // Alias per la modale (vuoti se manca categoria)
                'fabricAliases'  => $fabricAliases,
                'colorAliases'   => $colorAliases,
                'ambiguousColorTerms' => $ambiguousColorTerms,
            ]);
        }

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
            ->paginate(20)
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

        // --------- COERENZA descrizione ↔ mapping (per pagina) ----------
        $collection = $components->getCollection();
        $collection = $collection->map(function (Component $cmp) use ($fabricAliases, $colorAliases, $ambiguousColorTerms) {
            $coherence = $this->computeCoherenceForComponent($cmp, $fabricAliases, $colorAliases, $ambiguousColorTerms);
            // Attacchiamo metadati coerenti alla riga (usati dalla Blade)
            $cmp->coherence = $coherence; // ['status'=>'ok|info|warning','tooltip'=>'...']
            return $cmp;
        });
        $components->setCollection($collection);

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
            'duplicates'   => $duplicatePairs,
            'matrix'       => $matrix,
            'tessuMissing' => false,
            'isDuplicate'  => fn($fid, $cid) => in_array([$fid, $cid], $duplicatePairs, true),
            // Alias (passati alla modale per il warning live)
            'fabricAliases'        => $fabricAliases,
            'colorAliases'         => $colorAliases,
            'ambiguousColorTerms'  => $ambiguousColorTerms,
        ]);
    }

    /**
     * GET /variables/{component}
     * Dettaglio minimale di un componente TESSU (READ ONLY)
     */
    public function show(Component $component): View
    {
        // Carattere difensivo: ci assicuriamo che sia TESSU
        $tessuCategoryId = ComponentCategory::query()
            ->where('code', 'TESSU')
            ->value('id');

        abort_unless($tessuCategoryId && (int)$component->category_id === (int)$tessuCategoryId, 404);

        $fabric = $component->fabric_id ? Fabric::find($component->fabric_id) : null;
        $color  = $component->color_id  ? Color::find($component->color_id)   : null;

        return view('pages.variables.show', compact('component', 'fabric', 'color'));
    }

    /**
     * POST /variables/{component}/mapping
     * Salva l'abbinamento tessuto×colore per un componente TESSU.
     * Ritorna JSON (success|errors) per la modale "Abbina".
     *
     * Permesso richiesto: product-variables.manage (rotte protette)
     */
    public function storeMapping(Request $request, Component $component): JsonResponse
    {
        // 1) Precondizione: il componente DEVE essere TESSU
        $tessuCategoryId = ComponentCategory::query()
            ->where('code', 'TESSU')
            ->value('id');

        if (!$tessuCategoryId || (int)$component->category_id !== (int)$tessuCategoryId) {
            return response()->json([
                'message' => 'Componente non valido: non appartiene alla categoria TESSU.',
            ], 422);
        }

        // 2) Validazione base (inline) + tessuto/colore attivi
        $data = $request->validate([
            'fabric_id' => ['required', 'integer', 'min:1', 'exists:fabrics,id'],
            'color_id'  => ['required', 'integer', 'min:1', 'exists:colors,id'],
        ], [], [
            'fabric_id' => 'tessuto',
            'color_id'  => 'colore',
        ]);

        $fabric = Fabric::where('id', $data['fabric_id'])->where('active', true)->first();
        $color  = Color::where('id', $data['color_id'])->where('active', true)->first();

        if (!$fabric) {
            return response()->json(['message' => 'Il tessuto selezionato non è attivo.'], 422);
        }
        if (!$color) {
            return response()->json(['message' => 'Il colore selezionato non è attivo.'], 422);
        }

        // 3) STRICT: unicità coppia (fabric_id, color_id) tra componenti TESSU (altri record)
        $conflict = Component::query()
            ->where('category_id', $tessuCategoryId)
            ->where('fabric_id', $data['fabric_id'])
            ->where('color_id',  $data['color_id'])
            ->where('id', '!=', $component->id)
            ->first();

        if ($conflict) {
            return response()->json([
                'message' => 'Coppia tessuto×colore già associata allo SKU ' . $conflict->code . ' (ID ' . $conflict->id . ').',
            ], 422);
        }

        // 4) No-op: se è già uguale non scriviamo (idempotenza)
        if ((int)$component->fabric_id === (int)$data['fabric_id'] &&
            (int)$component->color_id  === (int)$data['color_id']) {

            return response()->json([
                'ok'        => true,
                'no_change' => true,
                'component' => [
                    'id'          => $component->id,
                    'code'        => $component->code,
                    'description' => $component->description,
                    'fabric_id'   => $component->fabric_id,
                    'color_id'    => $component->color_id,
                    'fabric_name' => $fabric->name,
                    'color_name'  => $color->name,
                ],
            ]);
        }

        // 5) Persistenza
        $component->fabric_id = $data['fabric_id'];
        $component->color_id  = $data['color_id'];
        // opzionale: forziamo UoM a 'm' per coerenza dei tessuti
        if ($component->unit_of_measure !== 'm') {
            $component->unit_of_measure = 'm';
        }
        $component->save();

        return response()->json([
            'ok'        => true,
            'message'   => 'Abbinamento salvato con successo.',
            'component' => [
                'id'          => $component->id,
                'code'        => $component->code,
                'description' => $component->description,
                'fabric_id'   => $component->fabric_id,
                'color_id'    => $component->color_id,
                'fabric_name' => $fabric->name,
                'color_name'  => $color->name,
            ],
        ]);
    }

    /**
     * Crea in blocco i componenti categoria TESSU per le coppie (fabric×color)
     * selezionate nella modale “Crea componenti mancanti”.
     *
     * Regole:
     * - Categoria: TESSU (obbligatoria)
     * - Codice: prefisso TESSU + progressivo a 5 cifre (come generateCode)
     * - Unicità coppia (fabric_id, color_id) STRICT: se esiste → skip
     * - UoM forzata a 'm' (metri), is_active = true
     * - Descrizione: pattern con placeholder ":fabric" e ":color" (default “Tessuto :fabric :color”)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createMissingComponents(Request $request): JsonResponse
    {
        // 1) Validazione input (array di coppie)
        $payload = $request->validate([
            'pairs' => ['required','array','min:1'],
            'pairs.*.fabric_id' => ['required','integer','exists:fabrics,id'],
            'pairs.*.color_id'  => ['required','integer','exists:colors,id'],
            'description_pattern' => ['nullable','string','max:255'],
        ], [], [
            'pairs' => 'coppie',
            'pairs.*.fabric_id' => 'tessuto',
            'pairs.*.color_id'  => 'colore',
        ]);

        // 2) Categoria TESSU
        $category = ComponentCategory::query()->where('code','TESSU')->first();
        if (!$category) {
            return response()->json(['message'=>'Categoria TESSU non trovata.'], 422);
        }

        $descriptionPattern = $payload['description_pattern'] ?: 'Tessuto :fabric :color';

        // 3) Normalizza set coppie richieste (evita duplicati client)
        $requested = [];
        foreach ($payload['pairs'] as $p) {
            $key = $p['fabric_id'].'-'.$p['color_id'];
            $requested[$key] = ['fabric_id'=>(int)$p['fabric_id'], 'color_id'=>(int)$p['color_id']];
        }

        if (empty($requested)) {
            return response()->json(['message'=>'Nessuna coppia valida fornita.'], 422);
        }

        // 4) Carica mappe nome tessuto/colore per la descrizione
        $fabricNames = Fabric::whereIn('id', array_column($requested,'fabric_id'))->pluck('name','id')->all();
        $colorNames  = Color::whereIn('id', array_column($requested,'color_id'))->pluck('name','id')->all();

        // 5) Escludi coppie già esistenti (STRICT unicità)
        $existing = Component::query()
            ->where('category_id', $category->id)
            ->whereIn('fabric_id', array_column($requested,'fabric_id'))
            ->whereIn('color_id', array_column($requested,'color_id'))
            ->whereNotNull('fabric_id')->whereNotNull('color_id')
            ->get(['fabric_id','color_id'])
            ->map(fn($r) => $r->fabric_id.'-'.$r->color_id)
            ->all();

        $toCreate = [];
        foreach ($requested as $key => $pair) {
            if (in_array($key, $existing, true)) continue;
            $toCreate[] = $pair;
        }

        if (empty($toCreate)) {
            return response()->json([
                'ok'=>true,
                'created'=>0,
                'skipped'=>count($requested),
                'message'=>'Nessun componente creato: tutte le coppie erano già presenti.',
            ]);
        }

        // 6) Transazione + generazione codici sequenziali come generateCode
        $created = [];
        DB::transaction(function () use ($category, $descriptionPattern, $fabricNames, $colorNames, $toCreate, &$created) {

            // ultimo codice esistente per categoria (inclusi soft-deleted)
            $last = Component::withTrashed()
                ->where('category_id', $category->id)
                ->where('code', 'like', "{$category->code}-%")
                ->latest('code')
                ->value('code');

            $next = $last
                ? (intval(substr($last, strlen($category->code) + 1)) + 1)
                : 1;

            foreach ($toCreate as $pair) {
                $fid = (int)$pair['fabric_id'];
                $cid = (int)$pair['color_id'];

                // genera codice progressivo a 5 cifre
                $code = $category->code . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
                $next++;

                // compone descrizione sostituendo placeholder
                $desc = str_replace(
                    [':fabric', ':color'],
                    [$fabricNames[$fid] ?? ('Fabric '.$fid), $colorNames[$cid] ?? ('Color '.$cid)],
                    $descriptionPattern
                );

                $cmp = new Component();
                $cmp->category_id      = $category->id;
                $cmp->code             = $code;
                $cmp->description      = $desc;
                $cmp->unit_of_measure  = 'm';       // i tessuti sono in metri
                $cmp->is_active        = true;
                $cmp->fabric_id        = $fid;      // mapping subito impostato
                $cmp->color_id         = $cid;

                // opzionali/nullable: lascio null per non forzare dati fittizi
                // $cmp->material = 'tessuto';

                $cmp->save();

                $created[] = [
                    'id' => $cmp->id,
                    'code' => $cmp->code,
                    'description' => $cmp->description,
                    'fabric_id' => $fid,
                    'color_id'  => $cid,
                ];
            }
        });

        return response()->json([
            'ok'      => true,
            'message' => 'Componenti TESSU creati con successo.',
            'created' => $created,
            'created_count' => count($created),
            'skipped' => count($requested) - count($created),
        ]);
    }

    /**
     * Salva un nuovo Tessuto dal modale “+ Nuovo Tessuto”.
     *
     * Validazione:
     * - name: obbligatorio, univoco in tabella fabrics (case-insensitive a seconda del collation)
     * - active: boolean (default true)
     * - markup_type / markup_value: opzionali; se le colonne non esistono, vengono ignorate
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeFabric(Request $request): JsonResponse
    {
        // Validazione input di base
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100', Rule::unique('fabrics', 'name')],
            'active'       => ['nullable', 'boolean'],
            // I campi markup sono opzionali: li validiamo ma li useremo solo se esistono in DB
            'markup_type'  => ['nullable', Rule::in(['fixed', 'percent'])],
            'markup_value' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'name'         => 'nome',
            'active'       => 'attivo',
            'markup_type'  => 'tipo maggiorazione',
            'markup_value' => 'valore maggiorazione',
        ]);

        // Creazione record
        $fabric = new Fabric();
        $fabric->name   = trim($data['name']);
        $fabric->active = array_key_exists('active', $data) ? (bool) $data['active'] : true;

        // Imposto surcharge solo se le colonne esistono fisicamente (evita errori SQL se non hai ancora migrato)
        if (Schema::hasColumn('fabrics', 'surcharge_type') && Schema::hasColumn('fabrics', 'surcharge_value')) {
            // Se non inviati, lascio null / default DB
            $fabric->surcharge_type  = $data['markup_type']  ?? null;
            $fabric->surcharge_value = $data['markup_value'] ?? null;
        }

        $fabric->save();

        return response()->json([
            'ok'     => true,
            'message'=> 'Tessuto creato con successo.',
            'fabric' => [
                'id'           => $fabric->id,
                'name'         => $fabric->name,
                'active'       => (bool) $fabric->active,
                'markup_type'  => $fabric->markup_type ?? null,
                'markup_value' => $fabric->markup_value ?? null,
            ],
        ]);
    }
    
    /**
     * Salva un nuovo Colore dalla modale “+ Nuovo Colore”.
     *
     * - name: obbligatorio, univoco in colors.name
     * - hex:  opzionale; se la colonna esiste, validiamo formato (#RGB o #RRGGBB) e unicità
     * - active: boolean (default true)
     * - markup_*: opzionali; usati SOLO se le colonne esistono
     */
    public function storeColor(Request $request): JsonResponse
    {
        // Regole base
        $rules = [
            'name'         => ['required', 'string', 'max:100', Rule::unique('colors', 'name')],
            'active'       => ['nullable', 'boolean'],
            'markup_type'  => ['nullable', Rule::in(['fixed', 'percent'])],
            'markup_value' => ['nullable', 'numeric', 'min:0'],
        ];

        // Validazione HEX solo se la colonna esiste
        if (Schema::hasColumn('colors', 'hex')) {
            $rules['hex'] = [
                'nullable',
                'string',
                // #RGB o #RRGGBB (con o senza # in input)
                'regex:/^#?([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
                Rule::unique('colors', 'hex'),
            ];
        }

        $data = $request->validate($rules, [], [
            'name'         => 'nome',
            'hex'          => 'colore (HEX)',
            'active'       => 'attivo',
            'markup_type'  => 'tipo maggiorazione',
            'markup_value' => 'valore maggiorazione',
        ]);

        $color = new Color();
        $color->name   = trim($data['name']);
        $color->active = array_key_exists('active', $data) ? (bool)$data['active'] : true;

        // HEX: salvo solo se la colonna esiste; normalizzo a #RRGGBB
        if (Schema::hasColumn('colors', 'hex')) {
            $hex = $data['hex'] ?? null;
            if ($hex) {
                $color->hex = $this->normalizeHex($hex);
            }
        }

        // Markup opzionale
        if (Schema::hasColumn('colors', 'surcharge_type') && Schema::hasColumn('colors', 'surcharge_value')) {
            $color->surcharge_type  = $data['markup_type']  ?? null;
            $color->surcharge_value = $data['markup_value'] ?? null;
        }

        $color->save();

        return response()->json([
            'ok'     => true,
            'message'=> 'Colore creato con successo.',
            'color'  => [
                'id'           => $color->id,
                'name'         => $color->name,
                'hex'          => $color->hex ?? null,
                'active'       => (bool)$color->active,
                'markup_type'  => $color->markup_type ?? null,
                'markup_value' => $color->markup_value ?? null,
            ],
        ]);
    }

    /**
     * Normalizza un HEX in formato #RRGGBB (accetta #RGB o #RRGGBB, con/ senza #).
     */
    private function normalizeHex(string $hex): string
    {
        $h = ltrim(trim($hex), '#');
        if (strlen($h) === 3) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }
        return '#'.strtoupper($h);
    }

    /* ====================== HELPERS COERENZA ====================== */

    /**
     * Costruisce gli alias per-ID a partire da:
     *  - nomi ufficiali DB (sempre inclusi)
     *  - sinonimi da config (fase A)
     */
    private function buildAliases($fabrics, $colors): array
    {
        $cfg = config('fabric_color_aliases');

        // Fabrics: per-ID => [alias...]
        $fabricAliases = [];
        foreach ($fabrics as $f) {
            $canon = $this->norm($f->name);
            $aliases = [$canon];
            $extra   = $cfg['fabric_synonyms_map'][$canon] ?? [];
            foreach ($extra as $a) $aliases[] = $this->norm($a);
            $fabricAliases[$f->id] = array_values(array_unique($aliases));
        }

        // Colors: per-ID => [alias...]; costruiamo tramite mapping alias=>canonical
        // 1) Raggruppiamo gli alias "sicuri" per canonical
        $canonToSyn = [];
        foreach ($cfg['color_synonyms_to_canonical'] as $alias => $canonical) {
            $canonToSyn[$this->norm($canonical)][] = $this->norm($alias);
        }

        $colorAliases = [];
        foreach ($colors as $c) {
            $canon = $this->norm($c->name);
            $aliases = [$canon];
            foreach ($canonToSyn[$canon] ?? [] as $a) $aliases[] = $a;
            $colorAliases[$c->id] = array_values(array_unique($aliases));
        }

        // Termini ambigui (già normalizzati)
        $ambiguous = array_map(fn($t) => $this->norm($t), $cfg['ambiguous_colors'] ?? []);

        return [$fabricAliases, $colorAliases, $ambiguous];
    }

    /** Normalizza stringhe: lowercase + rimozione accenti + solo lettere/numeri/spazi */
    private function norm(?string $s): string
    {
        $s = (string) $s;
        $s = Str::of($s)->lower()->ascii()->value(); // rimuove accenti
        // sostituisce tutto ciò che non è lettera/numero con spazio
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? '';
        // comprime spazi
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        return $s;
    }

    /**
     * Scansiona la descrizione e valuta coerenza col mapping corrente.
     * Restituisce ['status'=>'ok|info|warning','tooltip'=>'...'].
     */
    private function computeCoherenceForComponent(Component $cmp, array $fabricAliases, array $colorAliases, array $ambiguousColorTerms): array
    {
        $desc = $this->norm($cmp->description ?? '');

        // Nessuna descrizione = neutro (OK)
        if ($desc === '') {
            return ['status'=>'ok','tooltip'=>'Descrizione vuota o neutra.'];
        }

        // Cerca match: per-ID => [alias trovati...]
        $foundFabrics = $this->scanMatches($desc, $fabricAliases);
        $foundColors  = $this->scanMatches($desc, $colorAliases);

        // Termini ambigui (solo INFO)
        $foundAmbig = $this->scanAmbiguous($desc, $ambiguousColorTerms);

        $hasMap = $cmp->fabric_id && $cmp->color_id;

        // Tooltip di dettaglio
        $notes = [];

        if (!empty($foundFabrics)) {
            $ids = implode(', ', array_keys($foundFabrics));
            $notes[] = 'Tessuto nel testo: '.$this->humanizeFound($foundFabrics);
        }
        if (!empty($foundColors)) {
            $notes[] = 'Colore nel testo: '.$this->humanizeFound($foundColors);
        }
        if (!empty($foundAmbig)) {
            $notes[] = 'Termini ambigui: '.implode(', ', $foundAmbig);
        }

        if ($hasMap) {
            $fid = (int)$cmp->fabric_id;
            $cid = (int)$cmp->color_id;

            $fabricConflict = $this->hasConflict($foundFabrics, $fid);
            $colorConflict  = $this->hasConflict($foundColors,  $cid);

            // CASI WARNING: testo esplicito che contraddice il mapping
            if ($fabricConflict || $colorConflict) {
                $notes[] = 'Mapping attuale: tessuto ID '.$fid.' × colore ID '.$cid;
                return ['status'=>'warning', 'tooltip'=>implode(' | ', $notes) ?: 'Incongruenza nome ↔ mapping.'];
            }

            // Nessun conflitto: ma se il testo cita solo uno dei due, o solo ambigui → INFO
            $mentionsFabric = !empty($foundFabrics);
            $mentionsColor  = !empty($foundColors);

            if (($mentionsFabric xor $mentionsColor) || (!($mentionsFabric || $mentionsColor) && !empty($foundAmbig))) {
                $notes[] = 'Mapping attuale: tessuto ID '.$fid.' × colore ID '.$cid;
                return ['status'=>'info', 'tooltip'=>implode(' | ', $notes) ?: 'Informazione parziale nel nome.'];
            }

            // OK: neutro o coerente con mapping
            return ['status'=>'ok', 'tooltip'=>implode(' | ', $notes) ?: 'Coerenza OK.'];
        }

        // Nessun mapping: se il nome suggerisce qualcosa → INFO, altrimenti OK
        if (!empty($foundFabrics) || !empty($foundColors) || !empty($foundAmbig)) {
            return ['status'=>'info', 'tooltip'=>implode(' | ', $notes) ?: 'La descrizione suggerisce un possibile mapping.'];
        }

        return ['status'=>'ok', 'tooltip'=>'Descrizione neutra.'];
    }

    /** Ritorna per-ID gli alias che hanno fatto match nella descrizione */
    private function scanMatches(string $desc, array $aliasesById): array
    {
        $hits = [];
        foreach ($aliasesById as $id => $aliases) {
            foreach ($aliases as $alias) {
                if ($alias === '') continue;
                // regex con word boundary per multi-parola
                $pattern = '/\b'.preg_quote($alias,'/').'\b/u';
                if (preg_match($pattern, $desc) === 1) {
                    $hits[$id][] = $alias;
                }
            }
        }
        return $hits;
    }

    /** Ritorna termini ambigui presenti */
    private function scanAmbiguous(string $desc, array $ambiguous): array
    {
        $found = [];
        foreach ($ambiguous as $term) {
            if ($term === '') continue;
            $pattern = '/\b'.preg_quote($term,'/').'\b/u';
            if (preg_match($pattern, $desc) === 1) {
                $found[] = $term;
            }
        }
        return $found;
    }

    /** TRUE se sono presenti match di ID diversi dal mappedId */
    private function hasConflict(array $foundById, int $mappedId): bool
    {
        if (empty($foundById)) return false;
        // se cita SOLO il mappedId -> no conflitto
        $ids = array_keys($foundById);
        return !(count($ids) === 1 && (int)$ids[0] === $mappedId);
    }

    /** Converte found per-ID in stringa umana: "ID 3 (alias1, alias2); ID 8 (...)" */
    private function humanizeFound(array $foundById): string
    {
        $parts = [];
        foreach ($foundById as $id => $aliases) {
            $parts[] = 'ID '.$id.' ('.implode(', ', array_unique($aliases)).')';
        }
        return implode('; ', $parts);
    }
}
