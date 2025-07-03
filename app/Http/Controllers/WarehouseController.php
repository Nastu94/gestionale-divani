<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;

/**
 * Controller REST per la gestione dei magazzini.
 * 
 */
class WarehouseController extends Controller
{
    /**
     * Mostra la lista paginata dei magazzini.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Ordiniamo alfabeticamente e paginiamo (15 righe a pagina)
        $warehouses = Warehouse::paginate(15);

        return view('pages.warehouse.index', compact('warehouses'));
    }

    /**
     * Il form di creazione è un modale dentro la index,
     * quindi qui basta reindirizzare.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function create()
    {
        //
    }

    /**
     * Salva un nuovo magazzino.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Validazione input
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:warehouses,code',
            'name' => 'required|string|max:80',
            'type' => 'required|string|in:stock,commitment,scrap',
        ]);

        // Forziamo lo stato iniziale a “attivo”
        $validated['is_active'] = true;

        // Persistenza
        Warehouse::create($validated);

        // Redirect con flash message
        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Magazzino creato con successo.');
    }

    /**
     * Mostra il dettaglio di un singolo magazzino.
     *
     * @param  \App\Models\Warehouse $warehouse
     * @return \Illuminate\View\View
     */
    public function show(Warehouse $warehouse)
    {
        //
    }

    /**
     * L’edit completo non è previsto: ritorniamo 404.
     */
    public function edit()
    {
        abort(404);
    }

    /**
     * Aggiorna lo stato “attivo/inattivo” di un magazzino.
     *
     * Il form inline invia solo il flag is_active.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \App\Models\Warehouse    $warehouse
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        // Toggle attivo/inattivo
        $warehouse->update([
            'is_active' => $request->boolean('is_active'),
        ]);

        $msg = $warehouse->is_active
            ? 'Magazzino riattivato.'
            : 'Magazzino disattivato.';

        return redirect()
            ->route('warehouses.index')
            ->with('success', $msg);
    }

    /**
     * Cancella definitivamente un magazzino.
     * Valuta SoftDeletes se vuoi tenere lo storico.
     *
     * @param  \App\Models\Warehouse $warehouse
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Warehouse $warehouse)
    {
        $warehouse->delete();

        return redirect()
            ->route('warehouses.index')
            ->with('success', 'Magazzino eliminato definitivamente.');
    }
}
