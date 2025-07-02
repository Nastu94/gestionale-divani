<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\ComponentSupplier;
use App\Models\Component;
use App\Models\Supplier;

class PriceListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // ...
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // ...
    }

    /**
     * Salva (o aggiorna) la relazione componente‑fornitore con prezzo e lead‑time.
     * Se la validazione fallisce, si riapre il modale con i valori precedenti
     * sfruttando la sessione e la direttiva old().
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'component_id' => ['required', 'exists:components,id'],
            'supplier_id'  => ['required', 'exists:suppliers,id'],
            'price'        => ['required', 'numeric', 'min:0'],
            'lead_time'    => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            Log::warning('[PriceListController@store] validation FAILED', $validator->errors()->toArray());

            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput()                 
                ->with('supplier_modal', true); 
        }

        $data = $validator->validated();

        try {
            $pivot = ComponentSupplier::updateOrCreate(
                [
                    'component_id' => $data['component_id'],
                    'supplier_id'  => $data['supplier_id'],
                ],
                [
                    'last_cost'      => $data['price'],
                    'lead_time_days' => $data['lead_time'],
                ]
            );

            return redirect()
                ->route('components.index')
                ->with('success', 'Componente aggiunto / aggiornato nel listino del fornitore.');

        } catch (\Throwable $e) {
            Log::error('[PriceListController@store] EXCEPTION', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['unexpected' => 'Errore inatteso, controlla i log.'])
                ->with('supplier_modal', true);
        }
    }

    /**
     * Salva in bulk le relazioni componente-fornitore con prezzo e lead-time.
     *
     * @param  \Illuminate\Http\Request  $req
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkStore(Request $req, Supplier $supplier): JsonResponse
    {
        $data = $req->validate([
            'items'                => ['required','array','min:1'],
            'items.*.component_id' => ['required','integer', Rule::exists('components','id')],
            'items.*.price'        => ['required','numeric','min:0'],
            'items.*.lead_time'    => ['required','integer','min:0'],
        ]);

        $pivot = collect($data['items'])
            ->mapWithKeys(fn($r) => [
                $r['component_id'] => [
                    'last_cost'      => $r['price'],
                    'lead_time_days' => $r['lead_time'],
                ],
            ])->toArray();

        $supplier->components()->syncWithoutDetaching($pivot);

        return response()->json(['message'=>'Componenti salvati','count'=>count($pivot)]);
    }

    /**
     * Ritorna prezzo e lead-time per la coppia componente↔fornitore (JSON).
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetch(Request $request)
    {
        $data = $request->validate([
            'component_id' => ['required', 'exists:components,id'],
            'supplier_id'  => ['required', 'exists:suppliers,id'],
        ]);

        $pivot = ComponentSupplier::select('last_cost', 'lead_time_days')
                ->where($data)
                ->first();

                
        return response()->json([
            'found'      => (bool) $pivot,
            'price'      => $pivot ? $pivot->last_cost : null,
            'lead_time'  => $pivot ? $pivot->lead_time_days : null,
        ]);
    }

    /**
     * Elenco dei fornitori per un componente specifico.
     * 
     * @param  \App\Models\Component  $component
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Component $component): JsonResponse
    {

        /* fornitori con campi pivot ------------------------------------ */
        $suppliers = $component->suppliers()
            ->withPivot(['last_cost', 'lead_time_days'])
            ->get();

        /* risposta finale ---------------------------------------------- */
        return response()->json([
            'meta' => [
                'id'          => $component->id,
                'code'        => $component->code,
                'description' => $component->description,
            ],
            'data' => $suppliers,
        ]);
    }

    /**
     * Elenco dei componenti per un fornitore specifico.
     * 
     * @param  \App\Models\Supplier  $supplier
     * @return \Illuminate\Http\JsonResponse
     */
    public function components(Supplier $supplier): JsonResponse
    {

        /* componenti con campi pivot */
        $components = $supplier->components()
            ->withPivot(['last_cost', 'lead_time_days'])
            ->orderBy('code')
            ->get();

        return response()->json([
            'meta' => [
                'id'   => $supplier->id,
                'name' => $supplier->name,
            ],
            'data' => $components,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(ComponentSupplier $componentSupplier)
    {
        // ...
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ComponentSupplier $componentSupplier)
    {
        // ...
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ComponentSupplier $componentSupplier)
    {
        // ...
    }

    /**
     * Rimuove una relazione componente↔fornitore.
     */
    public function destroy(Component $component, Supplier $supplier)
    {
        ComponentSupplier::where([
            'component_id' => $component->id,
            'supplier_id'  => $supplier->id,
        ])->delete();

        return response()->noContent();
    }
}