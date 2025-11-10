<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Component;
use App\Models\Fabric;
use App\Models\Color;
use App\Models\ProductFabricColorOverride;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class ProductController extends Controller
{
    /**
     * Mostra una lista di prodotti
     */
    public function index(Request $request)
    {
        // Parametri ammessi per ordinamento
        $allowedSorts = ['sku', 'name', 'price', 'is_active'];
        $allowedDirs  = ['asc', 'desc'];

        // Leggi da querystring (default: id asc)
        $sort    = $request->query('sort', 'id');
        $dir     = $request->query('dir', 'asc');
        $filters = $request->query('filter', []);
        if (! array_key_exists('is_active', $filters)) {
            $filters['is_active'] = 'on';
        }

        // Sanitizza
        if (! in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }
        if (! in_array($dir, $allowedDirs)) {
            $dir = 'asc';
        }

        // Recupera liste componenti per il modal (sempre ordinate per code)
        $components = Component::select('id', 'code', 'description', 'unit_of_measure')
                        ->orderBy('code')
                        ->get();

        // Costruisci la query sui prodotti
        $query = Product::with([
            'components:id,code,description,unit_of_measure'
        ])
        ->withTrashed(); // Include soft-deleted;

        // 6) Applica filtri
        if (! empty($filters['sku'])) {
            $query->where('sku', 'like', '%' . $filters['sku'] . '%');
        }
        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }
        if (! empty($filters['price'])) {
            $query->where('price', $filters['price']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active'] === 'on' ? true : false);
        }

        // 7) Applica ordinamento
        $query->orderBy($sort, $dir);

        // 8) Pagina e mantieni le querystring
        $products = $query
            ->paginate(20)
            ->appends($request->query());

        // 9) Passa tutto in view
        return view('pages.master-data.index-products', compact(
            'products', 'components', 'sort', 'dir', 'filters'
        ));
    }

    /**
     * Genera un nuovo SKU per il prodotto: prefisso + 8 caratteri casuali
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateCode(): JsonResponse
    {
        $prefix = 'PRD-';

        do {
            // Genera 4 lettere casuali A–Z
            $letters = '';
            for ($i = 0; $i < 4; $i++) {
                // chr(rand(65,90)) restituisce una lettera maiuscola casuale
                $letters .= chr(random_int(65, 90));
            }

            // Genera 4 cifre, tra 0000 e 9999 (sempre 4 caratteri)
            $digits = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            // Combina prefisso + lettere + cifre
            $sku = $prefix . $letters . $digits;

            // Verifica unicità nel DB
            $exists = Product::where('sku', $sku)->exists();
        } while ($exists);

        return response()->json(['code' => $sku]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Salva un nuovo prodotto e associa i componenti con quantità.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Messaggi di errore personalizzati
        $messages = [
            'sku.required'        => 'Il codice prodotto è obbligatorio.',
            'sku.string'          => 'Il codice deve essere una stringa.',
            'sku.max'             => 'Il codice non può superare i 64 caratteri.',
            'sku.unique'          => 'Questo codice è già in uso.',
            'name.required'       => 'Il nome del prodotto è obbligatorio.',
            'name.string'         => 'Il nome deve essere una stringa.',
            'name.max'            => 'Il nome non può superare i 255 caratteri.',
            'price.required'      => 'Il prezzo è obbligatorio.',
            'price.numeric'       => 'Il prezzo deve essere un numero.',
            'price.min'           => 'Il prezzo deve essere almeno 0.',
            'components.array'    => 'I componenti devono essere un array.',
            'components.*.id.required'       => 'Seleziona un componente.',
            'components.*.id.exists'         => 'Il componente selezionato non esiste.',
            'components.*.quantity.required' => 'Inserisci la quantità.',
            'components.*.quantity.integer'  => 'La quantità deve essere un numero intero.',
            'components.*.quantity.min'      => 'La quantità deve essere almeno 1.',
            'fabric_required_meters.required' => 'Indica i metri di tessuto necessari.',
            'fabric_required_meters.numeric'  => 'I metri di tessuto devono essere numerici.',
            'fabric_required_meters.min'      => 'I metri di tessuto non possono essere negativi.',
        ];

        // Regole di validazione
        $validator = Validator::make($request->all(), [
            'sku'         => ['required','string','max:64','unique:products,sku'],
            'name'        => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'price'       => ['required','numeric','min:0'],
            'fabric_required_meters'  => ['required', 'numeric', 'min:0', 'max:999.999'],
            'components'              => ['nullable','array'],
            'components.*.id'         => ['required_with:components','exists:components,id'],
            'components.*.quantity'   => ['required_with:components','numeric','min:1'],
            'is_active'   => ['nullable','in:on,0,1'],
        ], $messages);

        if ($validator->fails()) {
            Log::warning('Validation errors in ProductController@store', $validator->errors()->toArray());
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['is_active'] = $request->has('is_active');

        try {
            DB::beginTransaction();

            // Creazione del prodotto
            $product = Product::create([
                'sku'         => $data['sku'],
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'price'       => $data['price'],
                'is_active'   => $data['is_active'],
            ]);

            // Associazione componenti con quantità (pivot table)
            if (! empty($data['components'])) {
                $sync = [];
                foreach ($data['components'] as $item) {
                    $sync[$item['id']] = ['quantity' => $item['quantity']];
                }
                $product->components()->sync($sync);
            }

            /* 
            * Inserimento/aggiornamento riga BOM "placeholder TESSU"
            * - Seleziona il "primo" componente base (0×0) attivo.
            * - Salva in pivot ->quantity i metri unitari (fabric_required_meters).
            * - Imposta eventuali flag in pivot se le colonne esistono (is_variable, variable_slot).
            */
            $product->ensureTessuPlaceholderWithMeters((float) $data['fabric_required_meters']);

            if ($data['is_active'] === false) {
                $product->delete(); // soft-delete immediato
            }

            DB::commit();

            return redirect()
                ->route('products.index')
                ->with('success', 'Prodotto creato con successo.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in ProductController@store', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Errore durante la creazione del prodotto. Controlla i log.');
        }
    }

    /**
     * Duplica un prodotto e la sua BOM (product_components),
     * generando uno SKU ex-novo col pattern "PRD-LLLLNNNN".
     *
     * - Transazione per consistenza.
     * - Se il sorgente è soft-deleted, la copia viene creata ATTIVA (deleted_at = null).
     * - Ritorna JSON minimale: ok, id e sku della nuova copia.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Models\Product      $product  Prodotto sorgente (route-binding conTrashed lato Model)
     * @return \Illuminate\Http\JsonResponse
     */
    public function duplicate(Request $request, Product $product): JsonResponse
    {
        try {
            $copy = DB::transaction(function () use ($product) {
                // 1) Clona gli attributi base (replicate ignora PK e relazioni)
                $clone = $product->replicate();

                // 2) SKU univoco nuovo con pattern PRD-LLLLNNNN
                $clone->sku = $this->makeNewSkuString();

                // 3) Se il sorgente era soft-deleted, la copia NON deve esserlo
                if (Schema::hasColumn($clone->getTable(), 'deleted_at')) {
                    $clone->setAttribute('deleted_at', null);
                }

                // 4) Salvataggio della copia
                $clone->save();

                // 5) Copia BOM (pivot product_components)
                //    Carico la pivot per leggere quantity/is_variable/variable_slot
                $components = $product->components()
                    ->withPivot(['quantity', 'is_variable', 'variable_slot'])
                    ->get();

                foreach ($components as $component) {
                    // Dati pivot sicuri → includo solo colonne realmente presenti
                    $pivot = [
                        'quantity' => (float) ($component->pivot->quantity ?? 0),
                    ];

                    if (Schema::hasColumn('product_components', 'is_variable')) {
                        $pivot['is_variable'] = (bool) ($component->pivot->is_variable ?? false);
                    }
                    if (Schema::hasColumn('product_components', 'variable_slot')) {
                        $pivot['variable_slot'] = $component->pivot->variable_slot ?? null;
                    }

                    $clone->components()->attach($component->id, $pivot);
                }

                return $clone;
            });

            return response()->json([
                'ok'  => true,
                'id'  => $copy->id,
                'sku' => $copy->sku,
                'msg' => 'Prodotto duplicato correttamente.',
            ], 201);
        } catch (Throwable $e) {
            // In caso di errore, rispondi in JSON senza rompere l’interfaccia AJAX
            report($e);

            return response()->json([
                'ok'   => false,
                'msg'  => 'Errore durante la duplicazione del prodotto.',
                'hint' => $e->getMessage(), // utile in dev; rimuovi in produzione se preferisci
            ], 500);
        }
    }

    /**
     * Genera una stringa SKU univoca con pattern "PRD-LLLLNNNN".
     * Non tocca il metodo esistente generateCode(): evitiamo side-effects.
     *
     * @return string SKU univoco
     */
    protected function makeNewSkuString(): string
    {
        $prefix = 'PRD-';

        do {
            // 4 lettere maiuscole A–Z
            $letters = '';
            for ($i = 0; $i < 4; $i++) {
                $letters .= chr(random_int(65, 90)); // A..Z
            }

            // 4 cifre sempre a 4 caratteri
            $digits = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $sku = $prefix . $letters . $digits;
            $exists = Product::withTrashed()->where('sku', $sku)->exists();
        } while ($exists);

        return $sku;
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Aggiorna un prodotto esistente e riallinea i componenti.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product       $product
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Product $product)
    {
        // Messaggi personalizzati (stessi di store)
        $messages = [
            'sku.required'        => 'Il codice prodotto è obbligatorio.',
            'sku.string'          => 'Il codice deve essere una stringa.',
            'sku.max'             => 'Il codice non può superare i 64 caratteri.',
            'sku.unique'          => 'Questo codice è già in uso.',
            'name.required'       => 'Il nome del prodotto è obbligatorio.',
            'name.string'         => 'Il nome deve essere una stringa.',
            'name.max'            => 'Il nome non può superare i 255 caratteri.',
            'price.required'      => 'Il prezzo è obbligatorio.',
            'price.numeric'       => 'Il prezzo deve essere un numero.',
            'price.min'           => 'Il prezzo deve essere almeno 0.',
            'components.array'    => 'I componenti devono essere un array.',
            'components.*.id.required'       => 'Seleziona un componente.',
            'components.*.id.exists'         => 'Il componente selezionato non esiste.',
            'components.*.quantity.required' => 'Inserisci la quantità.',
            'components.*.quantity.integer'  => 'La quantità deve essere un numero intero.',
            'components.*.quantity.min'      => 'La quantità deve essere almeno 1.',
            'fabric_required_meters.required' => 'Indica i metri di tessuto necessari.',
            'fabric_required_meters.numeric'  => 'I metri di tessuto devono essere numerici.',
            'fabric_required_meters.min'      => 'I metri di tessuto non possono essere negativi.',
        ];

        // Regole di validazione (stessi di store, sku unico escludendo l’attuale)
        $validator = Validator::make($request->all(), [
            'sku'         => [
                'required','string','max:64',
                Rule::unique('products','sku')->ignore($product->id),
            ],
            'name'        => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'price'       => ['required','numeric','min:0'],
            'fabric_required_meters'  => ['required', 'numeric', 'min:0', 'max:999.999'],
            'components'              => ['nullable','array'],
            'components.*.id'         => ['required_with:components','exists:components,id'],
            'components.*.quantity'   => ['required_with:components','numeric','min:1'],
            'is_active'   => ['nullable','in:on,0,1'],
        ], $messages);

        if ($validator->fails()) {
            Log::warning('Validation errors in ProductController@update', $validator->errors()->toArray());
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $data['is_active'] = $request->has('is_active');

        try {
            DB::beginTransaction();

            // Update dei campi del prodotto
            $product->update([
                'sku'         => $data['sku'],
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'price'       => $data['price'],
                'is_active'   => $data['is_active'],
            ]);

            // Riallaccio i componenti (sincronizzo pivot)
            $sync = [];
            if (! empty($data['components'])) {
                foreach ($data['components'] as $item) {
                    $sync[$item['id']] = ['quantity' => $item['quantity']];
                }
            }
            $product->components()->sync($sync);

            /**
             * Inserimento/aggiornamento riga BOM "placeholder TESSU"
             * Come in store: quantity = fabric_required_meters, flag pivot opzionali se presenti.
             */
            $product->ensureTessuPlaceholderWithMeters((float) $data['fabric_required_meters']);

            if ($data['is_active'] === false) {
                $product->delete(); // soft-delete immediato
            } else {
                $product->restore(); // rimuove deleted_at se presente
            }

            DB::commit();

            return redirect()
                ->route('products.index')
                ->with('success', 'Prodotto aggiornato con successo.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in ProductController@update', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Errore durante l\'aggiornamento del prodotto. Controlla i log.');
        }
    }

    /**
     * Restituisce le variabili (whitelist) attualmente associate al prodotto.
     *
     * @note Questo endpoint serve alla UI per popolare la modale "Variabili".
     *       Ritorniamo sia gli ID (comodi per i checkbox), sia una lista "ricca" con nome.
     */
    public function getVariables(Product $product): JsonResponse
    {
        $fabricIds = $product->fabrics()->pluck('fabrics.id')
            ->map(fn($i)=>(int)$i)->values()->all();
        
        $colorIds  = $product->colors()->pluck('colors.id')
            ->map(fn($i)=>(int)$i)->values()->all();

        $defF = $product->defaultFabricId();
        $defC = $product->defaultColorId();

        if ($defF && !in_array($defF, $fabricIds, true)) {
            $fabricIds[] = $defF;
        }
        if ($defC && !in_array($defC, $colorIds, true)) {
            $colorIds[] = $defC;
        }

        return response()->json([
            'fabric_ids' => $fabricIds,
            'color_ids'  => $colorIds,
            'default_fabric_id' => $defF,
            'default_color_id'  => $defC,
        ]);
    }
    
    /**
     * Restituisce in JSON tutti gli override di maggiorazione per il prodotto.
     * Struttura:
     *  - fabrics:  [{fabric_id, surcharge_type, surcharge_value}]
     *  - colors:   [{color_id,  surcharge_type, surcharge_value}]
     *  - pairs:    [{fabric_id, color_id, surcharge_type, surcharge_value}]
     *
     * @note Δ negativo non previsto: lato UI mostriamo solo valori ≥ 0.
     */
    public function getVariableOverrides(Product $product): JsonResponse
    {
        // Carichiamo tutti gli override del prodotto
        $rows = ProductFabricColorOverride::query()
            ->where('product_id', $product->id)
            ->get(['id','scope','fabric_id','color_id','surcharge_type','surcharge_value']);

        $fabrics = [];
        $colors  = [];
        $pairs   = [];

        foreach ($rows as $r) {
            $data = [
                'id'              => $r->id,
                'surcharge_type'  => $r->surcharge_type,   // 'percent' | 'per_meter'
                'surcharge_value' => (float) $r->surcharge_value, // ≥ 0
            ];
            if ($r->scope === 'fabric') {
                $data['fabric_id'] = $r->fabric_id;
                $fabrics[] = $data;
            } elseif ($r->scope === 'color') {
                $data['color_id'] = $r->color_id;
                $colors[] = $data;
            } elseif ($r->scope === 'pair') {
                $data['fabric_id'] = $r->fabric_id;
                $data['color_id']  = $r->color_id;
                $pairs[] = $data;
            }
        }

        return response()->json(compact('fabrics','colors','pairs'), 200);
    }

    /**
     * Sincronizza le whitelist di variabili (tessuti/colori) per un prodotto.
     *
     * Regole:
     *  - Accetta array di id (facoltativi) per fabrics[] e colors[].
     *  - Valida l’esistenza e che siano attivi.
     *  - Sincronizza le pivot product_fabrics / product_colors.
     *  - NON tocca listini o override (step successivo).
     */
    public function updateVariables(Request $request, Product $product)
    {
        // Messaggi di errore comprensibili per l’utente finale
        $messages = [
            'fabrics.array'     => 'Formato non valido per i tessuti.',
            'fabrics.*.integer' => 'ID tessuto non valido.',
            'fabrics.*.exists'  => 'Alcuni tessuti non esistono o non sono attivi.',
            'colors.array'      => 'Formato non valido per i colori.',
            'colors.*.integer'  => 'ID colore non valido.',
            'colors.*.exists'   => 'Alcuni colori non esistono o non sono attivi.',
        ];

        // Validazione: esistono e sono attivi
        $data = $request->validate([
            'fabrics'   => ['sometimes','array'],
            'fabrics.*' => [
                'integer',
                Rule::exists('fabrics', 'id')->where(fn($q) => $q->where('active', 1)),
            ],
            'colors'    => ['sometimes','array'],
            'colors.*'  => [
                'integer',
                Rule::exists('colors', 'id')->where(fn($q) => $q->where('active', 1)),
            ],
        ], $messages);

        $fabricIds = array_values(array_unique($data['fabrics'] ?? []));
        $colorIds  = array_values(array_unique($data['colors']  ?? []));

        try {
            DB::transaction(function () use ($product, $fabricIds, $colorIds) {

                // -----------------------------------------------------------------
                // NOTE IMPORTANTI:
                // - Usciamo "puliti": sync() sostituisce l’elenco con quello passato.
                // - Non impostiamo campi extra pivot (surcharge_type/value/is_default):
                //   qui stiamo SOLO abilitando la selezione; gli override arrivano dopo.
                // - Se le tabelle pivot non avessero timestamp/campi extra, va comunque bene.
                // -----------------------------------------------------------------
                $product->fabrics()->sync($fabricIds);
                $product->colors()->sync($colorIds);
            });

            return back()->with('success', 'Variabili del prodotto aggiornate correttamente.');

        } catch (\Throwable $e) {
            Log::error('Errore updateVariables', [
                'product_id' => $product->id,
                'exception'  => $e->getMessage(),
            ]);
            return back()->with('error', 'Errore durante il salvataggio delle variabili.');
        }
    }

    /**
     * Salva/aggiorna gli override di maggiorazione (Δ ≥ 0) per il prodotto.
     * Input atteso:
     *  - fabrics:  array di {fabric_id, surcharge_type, surcharge_value}
     *  - colors:   array di {color_id,  surcharge_type, surcharge_value}
     *  - pairs:    array di {fabric_id, color_id, surcharge_type, surcharge_value}
     *
     * Validazioni:
     *  - scope coerenti (fabric/color/pair)
     *  - surcharge_type ∈ {percent, per_meter}
     *  - surcharge_value ≥ 0 (niente sconti qui)
     *  - gli ID devono esistere e, se vuoi, essere “whitelistati” per questo prodotto
     */
    public function updateVariableOverrides(Request $request, Product $product)
    {
        // Messaggi utente
        $messages = [
            'fabrics.array'                       => 'Formato non valido per override tessuti.',
            'colors.array'                        => 'Formato non valido per override colori.',
            'pairs.array'                         => 'Formato non valido per override coppie.',
            '*.surcharge_type.in'                 => 'Tipo maggiorazione non valido.',
            '*.surcharge_value.numeric'           => 'Il valore della maggiorazione deve essere numerico.',
            '*.surcharge_value.min'               => 'Le maggiorazioni non possono essere negative.',
            'fabrics.*.fabric_id.exists'          => 'Alcuni tessuti non esistono o non sono attivi.',
            'colors.*.color_id.exists'            => 'Alcuni colori non esistono o non sono attivi.',
            'pairs.*.fabric_id.exists'            => 'La coppia usa un tessuto inesistente o inattivo.',
            'pairs.*.color_id.exists'             => 'La coppia usa un colore inesistente o inattivo.',
        ];

        // Validazione base
        $data = $request->validate([
            'fabrics'                     => ['sometimes','array'],
            'fabrics.*.fabric_id'         => [
                'required','integer',
                Rule::exists('fabrics','id')->where(fn($q) => $q->where('active',1)),
            ],
            'fabrics.*.surcharge_type'    => ['required','in:percent,per_meter'],
            'fabrics.*.surcharge_value'   => ['required','numeric','min:0'],

            'colors'                      => ['sometimes','array'],
            'colors.*.color_id'           => [
                'required','integer',
                Rule::exists('colors','id')->where(fn($q) => $q->where('active',1)),
            ],
            'colors.*.surcharge_type'     => ['required','in:percent,per_meter'],
            'colors.*.surcharge_value'    => ['required','numeric','min:0'],

            'pairs'                       => ['sometimes','array'],
            'pairs.*.fabric_id'           => [
                'required','integer',
                Rule::exists('fabrics','id')->where(fn($q) => $q->where('active',1)),
            ],
            'pairs.*.color_id'            => [
                'required','integer',
                Rule::exists('colors','id')->where(fn($q) => $q->where('active',1)),
            ],
            'pairs.*.surcharge_type'      => ['required','in:percent,per_meter'],
            'pairs.*.surcharge_value'     => ['required','numeric','min:0'],
        ], $messages);

        // (Opzionale consigliato) vincola override alle whitelist del prodotto:
        // - un override fabric/color è consentito solo se quell’ID è nella whitelist del prodotto
        // - un override pair è consentito solo se entrambi sono whitelisted
        // Se non vuoi questo vincolo, commenta il blocco seguente.
        $whitelistedF = $product->fabrics()->pluck('fabrics.id')->all();
        $whitelistedC = $product->colors()->pluck('colors.id')->all();

        $reject = static function(array $ids, array $whitelist): array {
            return array_values(array_diff($ids, $whitelist));
        };

        $badF = isset($data['fabrics'])
            ? $reject(array_column($data['fabrics'], 'fabric_id'), $whitelistedF)
            : [];
        $badC = isset($data['colors'])
            ? $reject(array_column($data['colors'], 'color_id'), $whitelistedC)
            : [];
        $badPF = isset($data['pairs'])
            ? $reject(array_column($data['pairs'], 'fabric_id'), $whitelistedF)
            : [];
        $badPC = isset($data['pairs'])
            ? $reject(array_column($data['pairs'], 'color_id'), $whitelistedC)
            : [];

        if ($badF || $badC || $badPF || $badPC) {
            return back()->with('error',
                'Alcuni override non sono consentiti perché non inclusi nella whitelist del prodotto.'
            );
        }

        // Salvataggio atomico
        try {
            DB::transaction(function () use ($product, $data) {
                // FABRICS
                foreach (($data['fabrics'] ?? []) as $row) {
                    ProductFabricColorOverride::query()->updateOrCreate(
                        [
                            'product_id'  => $product->id,
                            'scope'       => 'fabric',
                            'fabric_id'   => $row['fabric_id'],
                            'color_id'    => null,
                        ],
                        [
                            'surcharge_type'  => $row['surcharge_type'],   // 'percent' | 'per_meter'
                            'surcharge_value' => $row['surcharge_value'],  // >= 0
                        ]
                    );
                }

                // COLORS
                foreach (($data['colors'] ?? []) as $row) {
                    ProductFabricColorOverride::query()->updateOrCreate(
                        [
                            'product_id'  => $product->id,
                            'scope'       => 'color',
                            'fabric_id'   => null,
                            'color_id'    => $row['color_id'],
                        ],
                        [
                            'surcharge_type'  => $row['surcharge_type'],
                            'surcharge_value' => $row['surcharge_value'],
                        ]
                    );
                }

                // PAIRS
                foreach (($data['pairs'] ?? []) as $row) {
                    ProductFabricColorOverride::query()->updateOrCreate(
                        [
                            'product_id'  => $product->id,
                            'scope'       => 'pair',
                            'fabric_id'   => $row['fabric_id'],
                            'color_id'    => $row['color_id'],
                        ],
                        [
                            'surcharge_type'  => $row['surcharge_type'],
                            'surcharge_value' => $row['surcharge_value'],
                        ]
                    );
                }
            });

            return back()->with('success', 'Override di maggiorazione salvati correttamente.');

        } catch (\Throwable $e) {
            Log::error('Errore updateVariableOverrides', [
                'product_id' => $product->id,
                'exception'  => $e->getMessage(),
            ]);
            return back()->with('error', 'Errore durante il salvataggio degli override.');
        }
    }

    /**
     * Restituisce l’elenco di TUTTI i tessuti/colori attivi (per popolare la UI).
     * Struttura: { fabrics: [{id,name}], colors: [{id,name}] }.
     */
    public function getVariableOptions(): JsonResponse
    {
        // Prendiamo i campi più comuni; se 'name' non esiste/è null
        // facciamo fallback a 'code' e infine ad "Fabric #id" / "Color #id".
        $fabrics = Fabric::query()
            ->where('active', 1)
            ->orderBy('name') // se 'name' non c'è, l'ordinamento cade su default DB ma non è critico
            ->get()
            ->map(fn($f) => [
                'id'   => $f->id,
                'name' => $f->name ?? $f->code ?? ('Tessuto #'.$f->id),
            ])
            ->values();

        $colors = Color::query()
            ->where('active', 1)
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->name ?? $c->code ?? ('Colore #'.$c->id),
            ])
            ->values();

        return response()->json(compact('fabrics','colors'), 200);
    }

    /**
     * Ripristina un prodotto soft-deleted e lo riattiva.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($id)
    {
        try {
            DB::beginTransaction();

            // Trovo con i soft-deleted
            $product = Product::withTrashed()->findOrFail($id);

            // Ripristino
            $product->restore();

            // Riattivo
            $product->update(['is_active' => true]);

            DB::commit();

            return redirect()
                ->route('products.index')
                ->with('success', 'Prodotto ripristinato con successo.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in ProductController@restore', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return back()
                ->with('error', 'Errore durante il ripristino del prodotto. Controlla i log.');
        }
    }

    /**
     * Soft-delete di un prodotto (lo disattiva e lo marca come cancellato).
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Product $product)
    {
        try {
            DB::beginTransaction();

            // Disattivo
            $product->update(['is_active' => false]);

            // Soft-delete
            $product->delete();

            DB::commit();

            return redirect()
                ->route('products.index')
                ->with('success', 'Prodotto disattivato con successo.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error in ProductController@destroy', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return back()
                ->with('error', 'Errore durante l\'eliminazione del prodotto. Controlla i log.');
        }
    }
}
