<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Component;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

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
        ];

        // Regole di validazione
        $validator = Validator::make($request->all(), [
            'sku'         => ['required','string','max:64','unique:products,sku'],
            'name'        => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'price'       => ['required','numeric','min:0'],
            'components'              => ['nullable','array'],
            'components.*.id'         => ['required_with:components','exists:components,id'],
            'components.*.quantity'   => ['required_with:components','integer','min:1'],
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
        $messages = [ /* … vedi sopra … */ ];

        // Regole di validazione (stessi di store, sku unico escludendo l’attuale)
        $validator = Validator::make($request->all(), [
            'sku'         => [
                'required','string','max:64',
                Rule::unique('products','sku')->ignore($product->id),
            ],
            'name'        => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'price'       => ['required','numeric','min:0'],
            'components'              => ['nullable','array'],
            'components.*.id'         => ['required_with:components','exists:components,id'],
            'components.*.quantity'   => ['required_with:components','integer','min:1'],
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
