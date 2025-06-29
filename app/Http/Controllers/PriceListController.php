<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
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