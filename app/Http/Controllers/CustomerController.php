<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        /* Leggi parametri query ------------------------------------- */
        $sort    = $request->input('sort', 'company');      // default: company
        $dir     = $request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc';
        $filter  = $request->input('filter.company');       // stringa o null

        /* Costruisci query ----------------------------------------- */
        $customers = Customer::query()
            ->with('addresses')                             // eager load
            ->when($filter, fn ($q,$v) =>
                $q->where('company','like',"%$v%"))         // filtro LIKE
            ->orderBy('company', $dir)                      // sempre company
            ->paginate(15)
            ->appends($request->query());                   // preserva query

        /* Passa variabili alla view ------------------------------- */
        return view('pages.master-data.index-customers', [
            'customers' => $customers,
            'sort'      => $sort,       // per <x-th-menu>
            'dir'       => $dir,
            'filters'   => ['company' => $filter],
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
     * Salva i dati del form per la creazione di un nuovo cliente e i suoi indirizzi.
     *
     * Valida i dati del form, crea il cliente e i suoi indirizzi in un'unica transazione.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {

        // CREO IL VALIDATOR manuale per poter loggare gli errori
        $validator = Validator::make($request->all(), [
            'company'                 => ['required','string','max:255'],
            'vat_number'              => ['nullable','string','max:50','unique:customers,vat_number'],
            'tax_code'                => ['nullable','string','max:50'],
            'email'                   => ['nullable','email','max:255'],
            'phone'                   => ['nullable','string','max:50'],
            'is_active'               => ['nullable','in:on,0,1'],
            'addresses'               => ['nullable','array'],
            'addresses.*.type'        => ['required_with:addresses','in:billing,shipping,other'],
            'addresses.*.address'     => ['required_with:addresses','string','max:255'],
            'addresses.*.city'        => ['required_with:addresses','string','max:100'],
            'addresses.*.postal_code' => ['required_with:addresses','string','max:5'],
            'addresses.*.country'     => ['required_with:addresses','string','max:100'],
        ],
            [
                'addresses.*.type.required_with' => 'Il tipo di indirizzo è obbligatorio quando si forniscono indirizzi.',
                'addresses.*.address.required_with' => 'L\'indirizzo è obbligatorio quando si forniscono indirizzi.',
                'addresses.*.city.required_with' => 'La città è obbligatoria quando si forniscono indirizzi.',
                'addresses.*.postal_code.required_with' => 'Il codice postale è obbligatorio quando si forniscono indirizzi.',
                'addresses.*.postal_code.max' => 'Il codice postale non può superare i 5 caratteri.',
                'addresses.*.country.required_with' => 'Il paese è obbligatorio quando si forniscono indirizzi.',
                'company.required' => 'Il nome della compagnia è obbligatorio.',
                'company.string' => 'Il nome della compagnia deve essere una stringa.',
                'company.max' => 'Il nome della compagnia non può superare i 255 caratteri.',
                'email.email' => 'L\'email deve essere un indirizzo email valido.',
                'vat_number.unique' => 'Il numero di partita IVA deve essere unico.',
            ]
        );

        // SE FALLISCE, loggo e torno indietro con gli errori
        if ($validator->fails()) {
            Log::warning('Validation errors in CustomerController@store', $validator->errors()->toArray());

            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Prendo i dati validati e trasformo is_active in boolean
        $validated = $validator->validated();
        $validated['is_active'] = $request->has('is_active');

        try {
            // TRANSAZIONE
            DB::beginTransaction();

            // CREAZIONE CUSTOMER
            $customer = Customer::create([
                'company'    => $validated['company'],
                'vat_number' => $validated['vat_number'],
                'tax_code'   => $validated['tax_code'],
                'email'      => $validated['email'],
                'phone'      => $validated['phone'],
                'is_active'  => $validated['is_active'],
            ]);

            // Verifica creazione
            if (! $customer->wasRecentlyCreated) {
                throw new \Exception('Customer was not created.');
            }

            // CREAZIONE INDIRIZZI (se presenti)
            if (! empty($validated['addresses'])) {
                foreach ($validated['addresses'] as $addr) {
                    $address = $customer->addresses()->create($addr);

                    if (! $address) {
                        throw new \Exception("Failed to create address of type {$addr['type']}");
                    }
                }
            }

            // COMMIT
            DB::commit();

            // Redirect con successo
            return redirect()
                ->route('customers.index')
                ->with('success', 'Cliente creato con successo.');

        } catch (\Throwable $e) {
            // ROLLBACK e log dell’eccezione
            DB::rollBack();
            Log::error('Error in CustomerController@store', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore durante la creazione. Controlla i log.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        //
    }

    /**
     * Aggiorna un Customer esistente.
     *
     * Valida i dati in ingresso, aggiorna il record in transazione,
     * rialloca gli indirizzi (elimino e ricreo quelli passati),
     * gestendo rollback in caso di errore.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer      $customer
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Customer $customer)
    {

        // Validator manuale per poter intercettare e loggare gli errori
        $validator = Validator::make($request->all(), [
            'company'                 => ['required', 'string', 'max:255'],
            'vat_number'              => ['nullable', 'string', 'max:50'],
            'tax_code'                => ['nullable', 'string', 'max:50'],
            'email'                   => ['nullable', 'email', 'max:255'],
            'phone'                   => ['nullable', 'string', 'max:50'],
            'is_active'               => ['nullable', 'in:on,0,1'],
            'addresses'               => ['nullable', 'array'],
            'addresses.*.type'        => ['required_with:addresses', 'in:billing,shipping,other'],
            'addresses.*.address'     => ['required_with:addresses', 'string', 'max:255'],
            'addresses.*.city'        => ['required_with:addresses', 'string', 'max:100'],
            'addresses.*.postal_code' => ['required_with:addresses', 'string', 'max:20'],
            'addresses.*.country'     => ['required_with:addresses', 'string', 'max:100'],
        ]);

        // Se la validazione fallisce, loggo e torno indietro con gli errori
        if ($validator->fails()) {
            Log::warning('Validation errors in CustomerController@update', $validator->errors()->toArray());

            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Dati validati e conversione di is_active in boolean
        $data = $validator->validated();
        $data['is_active'] = $request->has('is_active');

        try {
            // Transazione per garantire atomicità
            DB::beginTransaction();

            // Update del record customer
            $customer->update([
                'company'    => $data['company'],
                'vat_number' => $data['vat_number'],
                'tax_code'   => $data['tax_code'],
                'email'      => $data['email'],
                'phone'      => $data['phone'],
                'is_active'  => $data['is_active'],
            ]);

            // Riallocazione indirizzi: cancello quelli esistenti…
            $customer->addresses()->delete();

            // … e ne ricreo di nuovi, se forniti
            if (! empty($data['addresses'])) {
                foreach ($data['addresses'] as $addr) {
                    $newAddr = $customer->addresses()->create($addr);
                    if (! $newAddr) {
                        throw new \Exception("Failed to create address of type {$addr['type']} during update.");
                    }
                }
            }

            // Commit se tutto ok
            DB::commit();

            // Redirect con messaggio di successo
            return redirect()
                ->route('customers.index')
                ->with('success', 'Cliente aggiornato con successo.');

        } catch (\Throwable $e) {
            // Rollback e log dettagliato dell’errore
            DB::rollBack();
            Log::error('Error in CustomerController@update', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Errore durante l\'aggiornamento. Controlla i log.');
        }
    }

    /**
     * Rimuove definitivamente un Customer e i suoi indirizzi.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Customer $customer)
    {
        
        try {
            // BEGIN TRANSACTION
            DB::beginTransaction();

            // Conta quanti indirizzi ci sono (via query)
            $count = $customer->addresses()->count();

            // Provo a cancellarli con query builder (per escludere problemi di relazione)
            $deletedViaQB = DB::table('customer_addresses')
                ->where('customer_id', $customer->id)
                ->delete();

            // Rilancio un altro count per vedere se è sparito davvero
            $countAfter = $customer->addresses()->count();

            // Provo a cancellare il customer
            $res = $customer->delete();

            // COMMIT
            DB::commit();

            return redirect()
                ->route('customers.index')
                ->with('success', 'Cliente eliminato con successo.');

        } catch (\Throwable $e) {
            // ROLLBACK + log dell’errore
            DB::rollBack();
            Log::error('Error in CustomerController@destroy', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Errore durante l’eliminazione. Controlla i log.');
        }
    }

}
