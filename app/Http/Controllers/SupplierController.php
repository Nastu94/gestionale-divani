<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Mostra una lista di fornitori.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Definiamo i campi sui quali è permesso ordinare
        $allowedSorts = ['name', 'is_active'];

        // Leggiamo il parametro 'sort' e lo validiamo contro la whitelist
        $sort = $request->input('sort', 'name');
        if (! in_array($sort, $allowedSorts)) {
            $sort = 'name';
        }

        // Leggiamo la direzione, default 'asc', forziamo solo 'asc' o 'desc'
        $dir = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        // Leggiamo il filtro sul nome (il th-menu passa filter[name])
        $filterName = $request->input('filter.name');

        // Costruiamo la query di base, includendo i soft-deleted
        $query = Supplier::withTrashed();

        // Applichiamo il filtro sul nome, se fornito
        if ($filterName) {
            $query->where('name', 'like', "%{$filterName}%");
        }

        // Applichiamo l'ordinamento dinamico
        $query->orderBy($sort, $dir);

        // Eseguiamo la paginazione e manteniamo i parametri in query string
        $suppliers = $query
            ->paginate(15)
            ->appends($request->query());

        // Restituiamo la vista passando anche sort, dir e filters per th-menu
        return view('pages.master-data.index-suppliers', [
            'suppliers' => $suppliers,
            'sort'      => $sort,
            'dir'       => $dir,
            'filters'   => [
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
     * Salva un nuovo fornitore nel database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {

        // Array di messaggi custom: chiave = campo.regola
        $messages = [
            'name.required'           => 'Il nome del fornitore è obbligatorio.',
            'vat_number.unique' => 'Questa Partita IVA è già registrata per un altro fornitore.',
            'address.via.required_with'         => 'Devi inserire la via dell’indirizzo.',
            'address.city.required_with'        => 'Devi inserire la città.',
            'address.postal_code.required_with' => 'Devi inserire il CAP.',
            'address.country.required_with'     => 'Devi inserire il paese.',
        ];

        // Validator manuale per intercettare e loggare errori di validazione
        $validator = Validator::make($request->all(), [
            'name'           => ['required', 'string', 'max:255'],
            'vat_number'     => ['nullable','string','max:50','unique:suppliers,vat_number'],
            'tax_code'       => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'website'        => ['nullable', 'url', 'max:255'],
            'payment_terms'  => ['nullable', 'string'],
            'address'        => ['nullable','array'],
            'address.via'    => ['required_with:address','string','max:255'],
            'address.city'   => ['required_with:address','string','max:100'],
            'address.postal_code'=> ['required_with:address','string','max:20'],
            'address.country'=> ['required_with:address','string','max:100'],
            'is_active'      => ['nullable', 'in:on,0,1'],
        ]   , $messages);

        // Se la validazione fallisce, loggo e torno indietro con errori
        if ($validator->fails()) {
            Log::warning('Validation errors in SupplierController@store', $validator->errors()->toArray());

            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Dati validati e trasformazione checkbox in booleano
        $data = $validator->validated();
        $data['is_active'] = $request->has('is_active');

        try {
            // Inizio transazione
            DB::beginTransaction();

            // Creazione del fornitore
            $supplier = Supplier::create([
                'name'          => $data['name'],
                'vat_number'    => $data['vat_number'],
                'tax_code'      => $data['tax_code'],
                'email'         => $data['email'],
                'phone'         => $data['phone'],
                'website'       => $data['website'],
                'payment_terms' => $data['payment_terms'],
                'address'       => $data['address'],
                'is_active'     => $data['is_active'],
            ]);

            // Verifica che il record sia stato effettivamente creato
            if (! $supplier->wasRecentlyCreated) {
                throw new \Exception('Supplier was not created.');
            }

            // Commit della transazione
            DB::commit();

            // Redirect con messaggio di successo
            return redirect()
                ->route('suppliers.index')
                ->with('success', 'Fornitore creato con successo.');

        } catch (\Throwable $e) {
            // Rollback e log dell’eccezione
            DB::rollBack();
            Log::error('Error in SupplierController@store', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            // Redirect indietro con input e messaggio di errore
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore durante la creazione del fornitore. Controlla i log.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Supplier $supplier)
    {
        //
    }

    /**
     * Salva le modifiche a un fornitore esistente nel database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Supplier      $supplier
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Supplier $supplier)
    {

        // Array di messaggi custom: chiave = campo.regola
        $messages = [
            'name.required'           => 'Il nome del fornitore è obbligatorio.',
            'vat_number.unique' => 'Questa Partita IVA è già registrata per un altro fornitore.',
            'address.via.required_with'         => 'Devi inserire la via dell’indirizzo.',
            'address.city.required_with'        => 'Devi inserire la città.',
            'address.postal_code.required_with' => 'Devi inserire il CAP.',
            'address.country.required_with'     => 'Devi inserire il paese.',
        ];

        // Validator manuale
        $validator = Validator::make($request->all(), [
            'name'           => ['required', 'string', 'max:255'],
            'vat_number'     => [
                                    'nullable','string','max:50',
                                    Rule::unique('suppliers','vat_number')->ignore($supplier->id),
                                ],
            'tax_code'       => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'website'        => ['nullable', 'url', 'max:255'],
            'payment_terms'  => ['nullable', 'string'],
            'address'        => ['nullable','array'],
            'address.via'    => ['required_with:address','string','max:255'],
            'address.city'   => ['required_with:address','string','max:100'],
            'address.postal_code'=> ['required_with:address','string','max:20'],
            'address.country'=> ['required_with:address','string','max:100'],
            'is_active'      => ['nullable', 'in:on,0,1'],
        ], $messages);

        // Gestione errori di validazione
        if ($validator->fails()) {
            Log::warning('Validation errors in SupplierController@update', $validator->errors()->toArray());

            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Dati validati e checkbox → booleano
        $data = $validator->validated();
        $data['is_active'] = $request->has('is_active');

        try {
            // Transazione
            DB::beginTransaction();

            // Update del fornitore
            $supplier->update([
                'name'          => $data['name'],
                'vat_number'    => $data['vat_number'],
                'tax_code'      => $data['tax_code'],
                'email'         => $data['email'],
                'phone'         => $data['phone'],
                'website'       => $data['website'],
                'payment_terms' => $data['payment_terms'],
                'address'       => $data['address'],
                'is_active'     => $data['is_active'],
            ]);

            // Commit
            DB::commit();

            // Redirect con messaggio di conferma
            return redirect()
                ->route('suppliers.index')
                ->with('success', 'Fornitore aggiornato con successo.');

        } catch (\Throwable $e) {
            // Rollback e log dell’errore
            DB::rollBack();
            Log::error('Error in SupplierController@update', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore durante l\'aggiornamento del fornitore. Controlla i log.');
        }
    }

    /**
     * Restore a soft-deleted Supplier and mark it active.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($id)
    {

        try {
            DB::beginTransaction();

            // 1) Trova anche i soft-deleted
            $supplier = Supplier::withTrashed()->findOrFail($id);

            // 2) Ripristina
            $supplier->restore();

            // 3) Riattiva il fornitore
            $supplier->update(['is_active' => true]);

            DB::commit();

            return redirect()
                ->route('suppliers.index')
                ->with('success', 'Fornitore ripristinato con successo.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error in SupplierController@restore', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Errore durante il ripristino del fornitore. Controlla i log.');
        }
    }

    /**
     * Elimina un fornitore dal database con soft delete.
     *
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Supplier $supplier)
    {

        try {
            // 2) Inizio transazione
            DB::beginTransaction();

            // 3) Aggiorna is_active = false
            $supplier->update([
                'is_active' => false,
            ]);

            // 4) Esegui il soft–delete
            $deleted = $supplier->delete();

            // 5) Commit
            DB::commit();

            // 6) Redirect con messaggio di successo
            return redirect()
                ->route('suppliers.index')
                ->with('success', 'Fornitore eliminato e marcato come non attivo.');

        } catch (\Throwable $e) {
            // 7) Rollback in caso di errore
            DB::rollBack();

            Log::error('Error in SupplierController@destroy', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            // 8) Redirect indietro con messaggio di errore
            return redirect()
                ->back()
                ->with('error', 'Errore durante l’eliminazione del fornitore. Controlla i log.');
        }
    }
}
