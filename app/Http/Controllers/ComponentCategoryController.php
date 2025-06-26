<?php

namespace App\Http\Controllers;

use App\Models\ComponentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ComponentCategoryController extends Controller
{
    /**
     * Lista categorie con sorting e filtering su code e name.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Leggi parametri
        $sort = $request->input('sort', 'id');  // default: id
        $dir  = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        // Filtri
        $filters    = $request->input('filter', []);
        $filterCode = $filters['code'] ?? null;
        $filterName = $filters['name'] ?? null;

        // Costruisci query
        $query = ComponentCategory::query();

        // Applica filtri se presenti
        if ($filterCode) {
            $query->where('code', 'like', "%{$filterCode}%");
        }
        if ($filterName) {
            $query->where('name', 'like', "%{$filterName}%");
        }

        // Gestisci ordinamento
        // Solo questi campi possono essere passati via query
        if (in_array($sort, ['id','code','name'], true)) {
            $query->orderBy($sort, $dir);
        } else {
            // nel raro caso venga passato un sort non consentito,
            // ricaduta su id
            $query->orderBy('id', 'asc');
        }

        // Paginazione con preservazione della query-string
        $categories = $query
            ->paginate(15)
            ->appends($request->query());

        // Passaggio dati alla view
        return view('pages.master-data.index-component_categories', [
            'categories' => $categories,
            'sort'       => $sort,
            'dir'        => $dir,
            'filters'    => [
                'code' => $filterCode,
                'name' => $filterName,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Salva una nuova categoria.
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Messaggi personalizzati
        $messages = [
            'code.required' => 'Il codice è obbligatorio.',
            'code.string'   => 'Il codice deve essere una stringa.',
            'code.max'      => 'Il codice non può superare i 5 caratteri.',
            'code.unique'   => 'Questo codice è già in uso.',
            'name.required' => 'Il nome è obbligatorio.',
            'name.string'   => 'Il nome deve essere una stringa.',
            'name.max'      => 'Il nome non può superare i 100 caratteri.',
            'description.string' => 'La descrizione deve essere una stringa.',
        ];

        // Validazione dei dati in ingresso
        $validator = Validator::make($request->all(), [
            'code'        => ['required','string','max:5','unique:component_categories,code'],
            'name'        => ['required','string','max:100'],
            'description' => ['nullable','string'],
        ], $messages);

        // Se la validazione fallisce, log degli errori e ritorno alla form
        // con gli errori e i dati inseriti
        // Questo evita di perdere i dati inseriti dall'utente
        // e permette di correggere gli errori senza dover reinserire tutto
        if ($validator->fails()) {
            // Log degli errori di validazione
            Log::warning('Validation errors in ComponentCategoryController@store', $validator->errors()->toArray());
            return back()->withErrors($validator)->withInput();
        }

        // Se la validazione ha successo, procediamo con la creazione
        // della nuova categoria
        $data = $validator->validated();

        // Iniziamo una transazione per garantire l'integrità dei dati
        try {
            // Iniziamo la transazione
            DB::beginTransaction();

            // Creiamo la nuova categoria con i dati validati
            ComponentCategory::create($data);

            // Commit della transazione
            DB::commit();

            // Redirect alla lista delle categorie con un messaggio di successo
            return redirect()
                ->route('categories.index')
                ->with('success', 'Categoria creata con successo.');
        } catch (\Throwable $e) {
            // In caso di errore, rollback della transazione
            DB::rollBack();

            // Log dell'errore con il messaggio dell'eccezione
            Log::error('Error in ComponentCategoryController@store', ['exception'=>$e->getMessage()]);

            // Redirect alla form con un messaggio di errore
            return back()->withInput()->with('error','Errore durante la creazione. Controlla i log.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ComponentCategory $componentCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ComponentCategory $componentCategory)
    {
        //
    }

    /**
     * Aggiorna una categoria esistente.
     * 
     * @param Request $request
     * @param ComponentCategory $componentCategory
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ComponentCategory $componentCategory)
    {
        // Messaggi personalizzati
        $messages = [
            'code.required' => 'Il codice è obbligatorio.',
            'code.string'   => 'Il codice deve essere una stringa.',
            'code.max'      => 'Il codice non può superare i 5 caratteri.',
            'code.unique'   => 'Questo codice è già in uso per un’altra categoria.',
            'name.required' => 'Il nome è obbligatorio.',
            'name.string'   => 'Il nome deve essere una stringa.',
            'name.max'      => 'Il nome non può superare i 100 caratteri.',
            'description.string' => 'La descrizione deve essere una stringa.',
        ];

        // Validazione
        $validator = Validator::make($request->all(), [
            'code'        => [
                'required','string','max:5',
                Rule::unique('component_categories','code')->ignore($componentCategory->id),
            ],
            'name'        => ['required','string','max:100'],
            'description' => ['nullable','string'],
        ], $messages);

        // Se la validazione fallisce, log degli errori e ritorno alla form
        // con gli errori e i dati inseriti
        // Questo evita di perdere i dati inseriti dall'utente
        // e permette di correggere gli errori senza dover reinserire tutto
        if ($validator->fails()) {
            Log::warning('Validation errors in ComponentCategoryController@update', $validator->errors()->toArray());
            return back()->withErrors($validator)->withInput();
        }

        // Se la validazione ha successo, procediamo con l'aggiornamento
        // dei dati della categoria
        $data = $validator->validated();

        // Iniziamo una transazione per garantire l'integrità dei dati
        // in caso di errori durante l'aggiornamento
        try {
            // Iniziamo la transazione
            DB::beginTransaction();

            // Aggiorniamo la categoria con i dati validati
            $componentCategory->update($data);

            // Commit della transazione
            DB::commit();

            // Redirect alla lista delle categorie con un messaggio di successo
            return redirect()
                ->route('categories.index')
                ->with('success', 'Categoria aggiornata con successo.');
        } catch (\Throwable $e) { 
            // In caso di errore, rollback della transazione
            DB::rollBack();

            // Log dell'errore con il messaggio dell'eccezione
            Log::error('Error in ComponentCategoryController@update', ['exception'=>$e->getMessage()]);

            // Redirect alla form con un messaggio di errore
            return back()->withInput()->with('error','Errore durante l\'aggiornamento. Controlla i log.');
        }
    }

    /**
     * Elimina una categoria.
     * 
     * @param ComponentCategory $category
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ComponentCategory $category)
    {
        // Verifica se la categoria ha componenti associati
        if ($category->components()->exists()) {
            return back()->with('error', 'Impossibile eliminare la categoria perché contiene dei componenti.');
        }

        // Iniziamo una transazione per garantire l'integrità dei dati
        try {
            // Iniziamo la transazione
            DB::beginTransaction();

            // Elimina la categoria
            $category->delete();

            // Commit della transazione
            DB::commit();

            // Redirect alla lista delle categorie con un messaggio di successo
            return redirect()
                ->route('categories.index')
                ->with('success', 'Categoria eliminata con successo.');
        } catch (\Throwable $e) {
            // In caso di errore, rollback della transazione
            DB::rollBack();

            // Log dell'errore con il messaggio dell'eccezione
            Log::error('Error in ComponentCategoryController@destroy', ['exception'=>$e->getMessage()]);

            // Redirect alla lista con un messaggio di errore
            return back()->with('error','Errore durante l\'eliminazione. Controlla i log.');
        }
    }
}
