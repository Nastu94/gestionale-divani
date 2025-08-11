<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Seeder per la tabella 'roles'.
 * Crea i ruoli e assegna i permessi definiti.
 */
class RolesSeeder extends Seeder
{
    /**
     * Popola la tabella 'roles' e assegna ai ruoli i permessi definiti.
     */
    public function run()
    {
        // Definizione ruoli e relativi permessi
        $roles = [
            'Admin' => [
                // Admin ottiene tutti i permessi
                '*'
            ],
            'Supervisor' => [
                'users.view', 'users.create', 'users.update',
                'orders.customer.*', 'orders.supplier.*',
                'categories.*', 'components.*', 'products.*', 'price_lists.view',
                'customers.view', 'suppliers.view',
                'stock.*', 'alerts.*', 'warehouses.*',
                'reports.orders.*', 'reports.stock_levels', 'reports.stock_movements'
            ],
            'Commerciale' => [
                'orders.supplier.*', 'price_lists.*',
                'components.view', 'products.view',
                'customers.*', 'suppliers.*',
                'reports.orders.supplier'
            ],
            'Impiegato' => [
                'orders.customer.view', 'orders.customer.create', 'orders.customer.update',
                'orders.supplier.view', 'orders.supplier.create', 'orders.supplier.update',
                'categories.view', 'components.view', 'products.view', 'price_lists.view',
                'customers.view', 'suppliers.view', 'warehouses.view',
                'stock.*', 'reports.orders.customer', 'reports.stock_levels'
            ],
            'Magazziniere' => [
                'orders.customer.view', 'orders.supplier.view', 'orders.supplier.update',
                'components.view', 'products.view', 'customers.view', 'suppliers.view',
                'stock.view', 'stock.entry', 'stock.entryEdit', 'stock.exit', 'warehouses.view',
            ],
        ];

        foreach ($roles as $roleName => $perms) {
            // Crea il ruolo se non esiste
            $role = Role::firstOrCreate(['name' => $roleName]);

            // Se '*' assegna tutti i permessi
            if (in_array('*', $perms)) {
                $role->givePermissionTo(Permission::all());
            } else {
                // Espande i wildcard (es. 'orders.customer.*') e assegna
                $expanded = [];
                foreach ($perms as $p) {
                    if (str_ends_with($p, '.*')) {
                        $module = rtrim($p, '.*');
                        $expanded = array_merge($expanded, Permission::where('name', 'LIKE', "$module.%")->pluck('name')->toArray());
                    } else {
                        $expanded[] = $p;
                    }
                }
                $role->syncPermissions(array_unique($expanded));
            }

            // Pulisci cache dopo l’assegnazione
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }
}