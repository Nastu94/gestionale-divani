<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\ComponentCategory;
use App\Models\Supplier;
use App\Models\Component;
use App\Models\ComponentSupplier;

/**
 * Controller CRUD per la gestione dell’anagrafica Componenti.
 *
 * Ogni metodo è transazionale, logga le eccezioni e usa soft-delete
 * per garantire audit completo e possibilità di ripristino.
 */
class ComponentController extends Controller
{
    /**
     * Lista paginata di componenti (attivi + cestinati).
     *
     * @param  Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        /* Parametri query ------------------------------------------------ */
        $sort      = $request->input('sort', 'code');                 // campo sort
        $dir       = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $filters   = $request->input('filter', []);                   // array filtri

        /* Whitelist dei campi ordinabili */
        $allowedSorts = ['code','description','material','unit_of_measure','is_active','category'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'code';
        }

        /* Query base ---------------------------------------------------- */
        $components = Component::query()
            ->with(['category',
                    'stockLevels' => fn ($q) => $q->where('quantity', '>', 0)
                ])                                         // eager-load
            ->with('componentSuppliers')                               // eager-load
            /* ---- filtri colonna -------------------------------------- */
            ->when($filters['category']   ?? null,
                fn ($q,$v) => $q->whereHas('category',
                               fn ($q) => $q->where('name','like',"%$v%")))
            ->when($filters['code']       ?? null,
                fn ($q,$v) => $q->where('code','like',"%$v%"))
            ->when($filters['description']?? null,
                fn ($q,$v) => $q->where('description','like',"%$v%"))
            /* ---- ordinamento ----------------------------------------- */
            ->when($sort === 'category', function ($q) use ($dir) {
                $q->leftJoin('component_categories as cc',
                             'components.category_id','=','cc.id')
                  ->orderBy('cc.name', $dir)
                  ->select('components.*');
            }, function ($q) use ($sort,$dir) {
                $q->orderBy($sort,$dir);
            })
            ->withTrashed() // include soft-deleted
            ->paginate(15)
            ->appends($request->query()); // preserva sort+filtri
        
        /* Eager-load categorie e fornitori per il form */
        $categories = ComponentCategory::orderBy('name')->get();
        $suppliers = Supplier::select('id', 'name')
                    ->orderBy('name')
                    ->get();

        return view(
            'pages.master-data.index-components',
            compact('components', 'categories', 'sort', 'dir', 'filters', 'suppliers')
        );
    }

    /**
     * Salva un nuovo componente.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        Log::info('Dati ricevuti dal form:', $request->all());

        /* -------------------------------------------------------------
        | Normalizzazione decimali IT → EN (prima della validazione)
        | Gestisce:
        |  - "1.234,56" → "1234.56"  (formato it-IT con separatore migliaia)
        |  - "12,5"     → "12.5"
        |  - rimuove spazi e NBSP
        ------------------------------------------------------------- */
        $decimalFields = ['length','width','height','weight'];

        $norm = static function ($v) {
            if ($v === null || $v === '') return null;
            $s = preg_replace('/[ \x{00A0}]/u', '', (string) $v); // spazi + NBSP

            // Caso "1.234,56": rimuove migliaia, converte virgola in punto
            if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $s)) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
                return $s;
            }

            // Caso con sola virgola decimale: "12,5" → "12.5"
            if (str_contains($s, ',') && ! str_contains($s, '.')) {
                return str_replace(',', '.', $s);
            }

            // Caso "1.234" (solo punti come migliaia, niente decimali) → "1234"
            if (substr_count($s, '.') > 1 && ! str_contains($s, ',')) {
                return str_replace('.', '', $s);
            }

            // Altri casi (già in formato con punto o stringa numerica semplice)
            return $s;
        };

        $toMerge = [];
        foreach ($decimalFields as $f) {
            $toMerge[$f] = $norm($request->input($f));
        }
        $request->merge($toMerge);

        // Validazione dei campi
        // Usa messaggi personalizzati per una migliore UX
        $messages = [
            'category_id.required'   => 'La categoria è obbligatoria.',
            'category_id.exists'     => 'La categoria selezionata non esiste.',
            'code.required'          => 'Il codice è obbligatorio.',
            'code.unique'            => 'Questo codice esiste già.',
            'description.required'   => 'La descrizione è obbligatoria.',
            'unit_of_measure.required' => 'L’unità di misura è obbligatoria.',
            'length.numeric'         => 'La lunghezza deve essere numerica.',
            'width.numeric'          => 'La larghezza deve essere numerica.',
            'height.numeric'         => 'L’altezza deve essere numerica.',
            'weight.numeric'         => 'Il peso deve essere numerico.',
        ];

        // Validazione con messaggi personalizzati
        $validator = Validator::make(
            $request->all(),
            [
                'category_id'     => ['required', 'exists:component_categories,id'],
                'code'            => ['required', 'string', 'max:50', 'unique:components,code'],
                'description'     => ['required', 'string', 'max:255'],
                'material'        => ['nullable', 'string', 'max:100'],
                'length'          => ['nullable', 'numeric', 'min:0'],
                'width'           => ['nullable', 'numeric', 'min:0'],
                'height'          => ['nullable', 'numeric', 'min:0'],
                'weight'          => ['nullable', 'numeric', 'min:0'],
                'unit_of_measure' => ['required', 'string', 'max:10'],
                'is_active'       => ['nullable', 'in:on,0,1'],
            ],
            $messages
        );

        // Se la validazione fallisce, logga gli errori e torna indietro
        if ($validator->fails()) {
            Log::warning('Validation errors in ComponentController@store', $validator->errors()->toArray());

            return back()
                ->withErrors($validator)
                ->withInput();
        }

        // Se la validazione ha successo, prepara i dati per il salvataggio
        $data              = $validator->validated();
        $data['is_active'] = $request->has('is_active');

        // Inizia la transazione per garantire l'integrità dei dati
        try {
            DB::beginTransaction();

            $component = Component::create($data);

            if($data['is_active'] === false){
                $component->delete(); // soft-delete immediato
            }

            if (! $component->wasRecentlyCreated) {
                throw new \RuntimeException('Component was not created.');
            }

            DB::commit();

            return redirect()
                ->route('components.index')
                ->with('success', 'Componente creato con successo.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error in ComponentController@store', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Errore durante la creazione del componente. Controlla i log.');
        }
    }

    /**
     * Genera un nuovo codice per un componente basato sulla categoria.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateCode(Request $request)
    {
        $request->validate(['category_id' => 'required|exists:component_categories,id']);

        $cat = ComponentCategory::find($request->category_id);

        // trova ultimo codice esistente
        $last = Component::withTrashed()
                ->where('category_id', $cat->id)
                ->where('code', 'like', "{$cat->code}-%")
                ->latest('code')
                ->value('code');

        $next = $last
            ? intval(substr($last, strlen($cat->code) + 1)) + 1
            : 1;

        return response()->json([
            'code' => $cat->code . '-' . str_pad($next, 5, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Aggiorna un componente esistente.
     *
     * @param  Request    $request
     * @param  Component  $component (route–model binding)
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Component $component)
    {
        Log::info('Dati ricevuti dal form:', $request->all());

        /* -------------------------------------------------------------
        | Normalizzazione decimali IT → EN (prima della validazione)
        | Gestisce:
        |  - "1.234,56" → "1234.56"  (formato it-IT con separatore migliaia)
        |  - "12,5"     → "12.5"
        |  - rimuove spazi e NBSP
        ------------------------------------------------------------- */
        $decimalFields = ['length','width','height','weight'];

        $norm = static function ($v) {
            if ($v === null || $v === '') return null;
            $s = preg_replace('/[ \x{00A0}]/u', '', (string) $v); // spazi + NBSP

            // Caso "1.234,56": rimuove migliaia, converte virgola in punto
            if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $s)) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
                return $s;
            }

            // Caso con sola virgola decimale: "12,5" → "12.5"
            if (str_contains($s, ',') && ! str_contains($s, '.')) {
                return str_replace(',', '.', $s);
            }

            // Caso "1.234" (solo punti come migliaia, niente decimali) → "1234"
            if (substr_count($s, '.') > 1 && ! str_contains($s, ',')) {
                return str_replace('.', '', $s);
            }

            // Altri casi (già in formato con punto o stringa numerica semplice)
            return $s;
        };

        $toMerge = [];
        foreach ($decimalFields as $f) {
            $toMerge[$f] = $norm($request->input($f));
        }
        $request->merge($toMerge);

        // Validazione dei campi
        // Usa messaggi personalizzati per una migliore UX
        $messages = [
            'category_id.required'   => 'La categoria è obbligatoria.',
            'category_id.exists'     => 'La categoria selezionata non esiste.',
            'code.required'          => 'Il codice è obbligatorio.',
            'code.unique'            => 'Questo codice esiste già per un altro componente.',
            'description.required'   => 'La descrizione è obbligatoria.',
            'unit_of_measure.required' => 'L’unità di misura è obbligatoria.',
            'length.numeric'         => 'La lunghezza deve essere numerica.',
            'width.numeric'          => 'La larghezza deve essere numerica.',
            'height.numeric'         => 'L’altezza deve essere numerica.',
            'weight.numeric'         => 'Il peso deve essere numerico.',
        ];

        // Validazione con messaggi personalizzati
        // Usa Rule::unique per ignorare il componente corrente
        $validator = Validator::make(
            $request->all(),
            [
                'category_id' => ['required', 'exists:component_categories,id'],
                'code'        => [
                    'required', 'string', 'max:50',
                    Rule::unique('components', 'code')->ignore($component->id),
                ],
                'description'     => ['required', 'string', 'max:255'],
                'material'        => ['nullable', 'string', 'max:100'],
                'length'          => ['nullable', 'numeric', 'min:0'],
                'width'           => ['nullable', 'numeric', 'min:0'],
                'height'          => ['nullable', 'numeric', 'min:0'],
                'weight'          => ['nullable', 'numeric', 'min:0'],
                'unit_of_measure' => ['required', 'string', 'max:10'],
                'is_active'       => ['nullable', 'in:on,0,1'],
            ],
            $messages
        );

        // Se la validazione fallisce, logga gli errori e torna indietro
        if ($validator->fails()) {
            Log::warning('Validation errors in ComponentController@update', $validator->errors()->toArray());

            return back()
                ->withErrors($validator)
                ->withInput();
        }

        // Se la validazione ha successo, prepara i dati per l'aggiornamento
        $data              = $validator->validated();
        $data['is_active'] = $request->has('is_active');

        // Inizia la transazione per garantire l'integrità dei dati
        try {
            DB::beginTransaction();

            $component->update($data);

            if($data['is_active'] === true){
                $component->restore(); // rimuove deleted_at se presente
            } else {
                $component->delete(); // soft-delete
            }

            DB::commit();

            return redirect()
                ->route('components.index')
                ->with('success', 'Componente aggiornato con successo.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error in ComponentController@update', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Errore durante l’aggiornamento del componente. Controlla i log.');
        }
    }

    /**
     * Ripristina un componente soft-deleted e lo imposta attivo.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($id)
    {
        // Trova il componente soft-deleted
        // e lo ripristina impostando is_active a true.
        try {
            DB::beginTransaction();

            $component = Component::withTrashed()->findOrFail($id);

            $component->restore();            // rimuove deleted_at
            $component->update(['is_active' => true]); // riattiva

            DB::commit();

            return redirect()
                ->route('components.index')
                ->with('success', 'Componente ripristinato con successo.');
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error in ComponentController@restore', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return back()
                ->with('error', 'Errore durante il ripristino del componente. Controlla i log.');
        }
    }

    /**
     * Soft-delete: marca non attivo e imposta deleted_at.
     *
     * @param  Component  $component
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Component $component)
    {
        /* ─────────────────────────────────────  BLOCCO 409  */
        if ($component->products()->exists()) {
            $msg = 'Impossibile eliminare il componente perché è associato a uno o più prodotti.';

            // AJAX? → JSON 409 --  altrimenti redirect con errore flash
            return $request->expectsJson()
                ? response()->json(['message' => $msg], Response::HTTP_CONFLICT)      // 409
                : back()->with('error', $msg);
        }

        /* ─────────────────────────────────────  SOFT-DELETE  */
        try {
            DB::transaction(fn () => tap($component)
                ->update(['is_active' => false])
                ->delete());

            // AJAX => 200 OK, altrimenti redirect “classico”
            return $request->expectsJson()
                ? response()->json(['message' => 'ok'])
                : redirect()->route('components.index')
                            ->with('success', 'Componente eliminato con successo.');
        } catch (\Throwable $e) {
            Log::error('Eliminazione componente fallita', ['ex' => $e->getMessage()]);

            $fallback = 'Errore imprevisto durante l’eliminazione. Riprova o contatta l’admin.';

            return $request->expectsJson()
                ? response()->json(['message' => $fallback], 500)
                : back()->with('error', $fallback);
        }
    }
}
