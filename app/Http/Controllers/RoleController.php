<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

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
            'stock'              => 'Movimentazioni',
            'alerts'             => 'Avvisi',
            'price_lists'        => 'Listini',
            'reports.orders'     => 'Report Ordini',
            'reports'            => 'Report',
            'warehouses'         => 'Magazzini',
            'categories'         => 'Categorie',
            'product-prices'     => 'Prezzi Prodotto',
            'product-variables'  => 'Variabili Prodotto',
        ];

        // Mappa azione → [lettera, descrizione ITA]
        $actionMap = [
            'view'            => ['V', 'Visualizza'],
            'create'          => ['C', 'Crea'],
            'update'          => ['A', 'Aggiorna'],
            'delete'          => ['E', 'Elimina'],
            'entry'           => ['I', 'Entrata'],
            'entryEdit'       => ['ME', 'Modifica Entrata'],
            'exit'            => ['U', 'Uscita'],
            'manage'          => ['G', 'Gestisci'],
            'customer'        => ['CR', 'Cliente (report)'],
            'supplier'        => ['FR', 'Fornitore (report)'],
            'stock_levels'    => ['GR', 'Giacenze (report)'],
            'stock_movements' => ['MR', 'Movimenti (report)'],
            'rollback_item_phase' => ['R', 'Rollback fase articolo'],
            'manage_supply'    => ['GS', 'Gestisci Supply'],
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
     * Salva un nuovo ruolo con permessi e ruoli assegnabili.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'               => 'required|unique:roles,name',
            'permissions'        => 'required|array|min:1',
            'permissions.*'      => 'exists:permissions,id',
            'assignable_roles'   => 'sometimes|array',
            'assignable_roles.*' => 'string|exists:roles,name',
        ]);

        // Creo il ruolo passando esplicitamente guard_name = 'web'
        $role = Role::create([
            'name'       => $validated['name'],
            'guard_name' => 'web',
        ]);

        // Recupero i Permission model e li sincronizzo
        $perms = Permission::whereIn('id', $validated['permissions'])->get();
        $role->syncPermissions($perms);

        // Sincronizzo i ruoli “assignable”
        if (! empty($validated['assignable_roles'])) {
            $ids = Role::whereIn('name', $validated['assignable_roles'])
                       ->pluck('id')
                       ->toArray();
            $role->assignableRoles()->sync($ids);
        }

        return redirect()->route('roles.index')
                         ->with('success', 'Ruolo creato con successo.');
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
     * Aggiorna un ruolo esistente (nome, permessi e ruoli assegnabili).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Spatie\Permission\Models\Role  $role
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name'               => 'required|unique:roles,name,' . $role->id,
            'permissions'        => 'required|array|min:1',
            'permissions.*'      => 'exists:permissions,id',
            'assignable_roles'   => 'sometimes|array',
            'assignable_roles.*' => 'string|exists:roles,name',
        ]);

        // Aggiorno nome e permessi
        $role->update([
            'name'       => $validated['name'],
            'guard_name' => 'web',
        ]);

        $perms = Permission::whereIn('id', $validated['permissions'])->get();
        $role->syncPermissions($perms);

        // Aggiorno assignable_roles
        if (! empty($validated['assignable_roles'])) {
            $ids = Role::whereIn('name', $validated['assignable_roles'])
                       ->pluck('id')
                       ->toArray();
            $role->assignableRoles()->sync($ids);
        } else {
            $role->assignableRoles()->detach();
        }

        return redirect()->route('roles.index')
                         ->with('success', 'Ruolo aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        // Controllo se il ruolo è assegnato a qualche utente
        if ($role->users()->count() > 0) {
            return redirect()
                ->route('roles.index')
                ->withErrors('Impossibile eliminare il ruolo, è assegnato a degli utenti.');
        }

        // Elimino il ruolo
        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Ruolo eliminato con successo.');
    }
}
