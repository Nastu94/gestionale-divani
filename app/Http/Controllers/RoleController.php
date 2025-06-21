<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Visualizza la matrice Ruoli × Permessi.
     *
     * - Eager-load dei permessi per evitare il problema N+1.
     * - Costruisce dinamicamente l’header della tabella raggruppando i permessi
     *   per modulo (es. "orders.customer").
     */
public function index()
{
    $roles = Role::with('permissions')->orderBy('id')->get();
    $permissions = Permission::select('id','name','display_name','description')->get();

    $permissionsByModule = $permissions->groupBy(function ($perm) {
        // modulo.*.*  → raggruppa per i primi 2 segmenti
        $parts = explode('.', $perm->name);
        return implode('.', array_slice($parts, 0, min(count($parts) - 1, 2)));
    });

    // Dizionario ITA per le intestazioni
    $moduleLabels = [
        'users'              => 'Utenti',
        'roles'              => 'Ruoli',
        'orders.customer'    => 'Ordini Cliente',
        'orders.supplier'    => 'Ordini Fornitore',
        'customers'          => 'Clienti',
        'suppliers'          => 'Fornitori',
        'components'         => 'Componenti',
        'products'           => 'Prodotti',
        'stock'              => 'Magazzino',
        'alerts'             => 'Avvisi',
        'price_lists'        => 'Listini',
        'reports.orders'     => 'Report Ordini',
        'reports'            => 'Report',
    ];

    // Mappa azione → [lettera, descrizione ITA]
    $actionMap = [
        'view'            => ['V', 'Visualizza'],
        'create'          => ['C', 'Crea'],
        'update'          => ['A', 'Aggiorna'],
        'delete'          => ['E', 'Elimina'],
        'entry'           => ['I', 'Entrata'],
        'exit'            => ['U', 'Uscita'],
        'manage'          => ['G', 'Gestisci'],
        'customer'        => ['CR', 'Cliente (report)'],
        'supplier'        => ['FR', 'Fornitore (report)'],
        'stock_levels'    => ['GR', 'Giacenze (report)'],
        'stock_movements' => ['MR', 'Movimenti (report)'],
    ];

    return view('pages.roles.index', compact(
        'roles',
        'permissionsByModule',
        'moduleLabels',
        'actionMap'
    ));
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Salva un nuovo ruolo.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|unique:roles,name',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions']);

        return redirect()->route('roles.index')
                        ->with('success', 'Ruolo creato con successo');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Modifica un ruolo esistente.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name'        => 'required|unique:roles,name,' . $role->id,
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions']);

        return redirect()->route('roles.index')
                        ->with('success', 'Ruolo aggiornato con successo');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
