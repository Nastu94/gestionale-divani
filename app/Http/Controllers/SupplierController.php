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
     */
    public function index()
    {
        // Recupera tutti i fornitori dal database
        $suppliers = Supplier::paginate(15);

        // Restituisce la vista 'suppliers.index' con i fornitori recuperati
        return view('pages.master-data.index-suppliers', compact('suppliers'));
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
        // Log iniziale: confermo l’ingresso nel metodo store
        Log::info('SupplierController@store called', $request->all());

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

        // Log dei dati che andranno salvati
        Log::info('Supplier data validated', $data);

        // Ascolto delle query SQL per debug
        DB::listen(function ($query) {
            Log::info('SQL Executed', [
                'sql'      => $query->sql,
                'bindings' => $query->bindings,
                'time'     => $query->time,
            ]);
        });

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
            Log::info('Supplier created', ['id' => $supplier->id]);

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
        // Log iniziale
        Log::info('SupplierController@update called', array_merge(['id' => $supplier->id], $request->all()));

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

        // Log dei dati prima dell’update
        Log::info('Supplier data validated for update', $data);

        // Debug SQL
        DB::listen(function ($query) {
            Log::info('SQL Executed', [
                'sql'      => $query->sql,
                'bindings' => $query->bindings,
                'time'     => $query->time,
            ]);
        });

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
            Log::info('Supplier updated', ['id' => $supplier->id]);

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
     * Elimina un fornitore dal database.
     *
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Supplier $supplier)
    {
        // Log iniziale
        Log::info('SupplierController@destroy called', ['id' => $supplier->id]);

        try {
            // Transazione
            DB::beginTransaction();

            // Cancellazione del fornitore
            $deleted = $supplier->delete();
            Log::info('Supplier delete() return value', ['id' => $supplier->id, 'deleted' => $deleted]);

            // Commit
            DB::commit();

            return redirect()
                ->route('suppliers.index')
                ->with('success', 'Fornitore eliminato con successo.');

        } catch (\Throwable $e) {
            // Rollback e log dell’errore
            DB::rollBack();
            Log::error('Error in SupplierController@destroy', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Errore durante l’eliminazione del fornitore. Controlla i log.');
        }
    }
}
