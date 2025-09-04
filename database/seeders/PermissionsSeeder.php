<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Popola la tabella 'permissions' con i permessi definiti in Tabella Permessi Spatie,
     * includendo 'display_name' e 'description' per il front-end.
     * Utilizza updateOrCreate() per evitare duplicati e aggiornare i campi aggiuntivi.
     */
    public function run()
    {
        // Svuota la cache di Spatie per i permessi
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Elenco permessi con display_name e description
        $permissions = [
            ['name' => 'users.view',                  'display_name' => 'Visualizza utenti',                  'description' => 'Visualizza lista utenti e dettagli di un singolo utente.'],
            ['name' => 'users.create',                'display_name' => 'Crea utenti',                       'description' => 'Crea nuovi utenti.'],
            ['name' => 'users.update',                'display_name' => 'Modifica utenti',                   'description' => 'Modifica dati di un utente esistente.'],
            ['name' => 'users.delete',                'display_name' => 'Elimina utenti',                    'description' => 'Elimina un utente.'],

            ['name' => 'roles.manage',                'display_name' => 'Gestisci ruoli',                    'description' => 'Gestisce la creazione, modifica e cancellazione di ruoli con permessi preesistenti.'],

            ['name' => 'orders.customer.view',        'display_name' => 'Visualizza ordini cliente',        'description' => 'Visualizza ordini cliente.'],
            ['name' => 'orders.customer.create',      'display_name' => 'Crea ordini cliente',              'description' => 'Crea ordini cliente.'],
            ['name' => 'orders.customer.update',      'display_name' => 'Modifica ordini cliente',          'description' => 'Modifica ordini cliente.'],
            ['name' => 'orders.customer.delete',      'display_name' => 'Elimina ordini cliente',           'description' => 'Elimina ordini cliente.'],
            ['name' => 'orders.customer.rollback_item_phase', 'display_name' => 'Rollback fase articolo ordine cliente', 'description' => 'Rollback della fase di produzione di un articolo di un ordine cliente.'],

            ['name' => 'orders.supplier.view',        'display_name' => 'Visualizza ordini fornitore',      'description' => 'Visualizza ordini fornitore.'],
            ['name' => 'orders.supplier.create',      'display_name' => 'Crea ordini fornitore',            'description' => 'Crea ordini fornitore.'],
            ['name' => 'orders.supplier.update',      'display_name' => 'Modifica ordini fornitore',        'description' => 'Modifica ordini fornitore.'],
            ['name' => 'orders.supplier.delete',      'display_name' => 'Elimina ordini fornitore',         'description' => 'Elimina ordini fornitore.'],

            ['name' => 'customers.view',              'display_name' => 'Visualizza clienti',               'description' => 'Visualizza anagrafica clienti.'],
            ['name' => 'customers.create',            'display_name' => 'Crea clienti',                     'description' => 'Crea anagrafica clienti.'],
            ['name' => 'customers.update',            'display_name' => 'Modifica clienti',                 'description' => 'Modifica anagrafica clienti.'],
            ['name' => 'customers.delete',            'display_name' => 'Elimina clienti',                  'description' => 'Elimina anagrafica clienti.'],

            ['name' => 'suppliers.view',              'display_name' => 'Visualizza fornitori',             'description' => 'Visualizza anagrafica fornitori.'],
            ['name' => 'suppliers.create',            'display_name' => 'Crea fornitori',                   'description' => 'Crea anagrafica fornitori.'],
            ['name' => 'suppliers.update',            'display_name' => 'Modifica fornitori',               'description' => 'Modifica anagrafica fornitori.'],
            ['name' => 'suppliers.delete',            'display_name' => 'Elimina fornitori',                'description' => 'Elimina anagrafica fornitori.'],

            ['name' => 'categories.view',             'display_name' => 'Visualizza categorie',             'description' => 'Visualizza lista categorie di componenti.'],
            ['name' => 'categories.create',           'display_name' => 'Crea categorie',                   'description' => 'Crea nuove categorie di componenti.'],
            ['name' => 'categories.update',           'display_name' => 'Modifica categorie',               'description' => 'Modifica categorie di componenti esistenti.'],
            ['name' => 'categories.delete',           'display_name' => 'Elimina categorie',                'description' => 'Elimina categorie di componenti.'],

            ['name' => 'components.view',             'display_name' => 'Visualizza componenti',            'description' => 'Visualizza anagrafica componenti.'],
            ['name' => 'components.create',           'display_name' => 'Crea componenti',                  'description' => 'Crea nuovi componenti.'],
            ['name' => 'components.update',           'display_name' => 'Modifica componenti',              'description' => 'Modifica componenti esistenti.'],
            ['name' => 'components.delete',           'display_name' => 'Elimina componenti',               'description' => 'Elimina componenti.'],

            ['name' => 'products.view',               'display_name' => 'Visualizza prodotti',              'description' => 'Visualizza anagrafica prodotti.'],
            ['name' => 'products.create',             'display_name' => 'Crea prodotti',                    'description' => 'Crea nuovi prodotti.'],
            ['name' => 'products.update',             'display_name' => 'Modifica prodotti',                'description' => 'Modifica prodotti esistenti.'],
            ['name' => 'products.delete',             'display_name' => 'Elimina prodotti',                 'description' => 'Elimina prodotti.'],

            ['name' => 'stock.view',                  'display_name' => 'Visualizza giacenze',              'description' => 'Visualizza giacenze e storico movimenti di magazzino.'],
            ['name' => 'stock.entry',                 'display_name' => 'Registra entrate',                 'description' => 'Registra movimenti di entrata in magazzino.'],
            ['name' => 'stock.entryEdit',             'display_name' => 'Modifica entrate',                 'description' => 'Modifica movimenti di entrata in magazzino.'],
            ['name' => 'stock.exit',                  'display_name' => 'Registra uscite',                  'description' => 'Registra movimenti di uscita in magazzino.'],

            ['name' => 'warehouses.view',            'display_name' => 'Visualizza magazzini',             'description' => 'Visualizza lista magazzini.'],
            ['name' => 'warehouses.create',          'display_name' => 'Crea magazzini',                   'description' => 'Crea nuovi magazzini.'],
            ['name' => 'warehouses.update',          'display_name' => 'Modifica magazzini',               'description' => 'Modifica magazzini esistenti.'],
            ['name' => 'warehouses.delete',          'display_name' => 'Elimina magazzini',                'description' => 'Elimina magazzini dal sistema.'],

            ['name' => 'alerts.view',                 'display_name' => 'Visualizza avvisi',               'description' => 'Visualizza lista avvisi di magazzino.'],
            ['name' => 'alerts.create',               'display_name' => 'Crea avvisi',                     'description' => 'Crea nuovi avvisi per soglie di giacenza.'],
            ['name' => 'alerts.update',               'display_name' => 'Modifica avvisi',                 'description' => 'Modifica avvisi esistenti (soglie, messaggio).'],
            ['name' => 'alerts.delete',               'display_name' => 'Elimina avvisi',                  'description' => 'Elimina avvisi dal sistema.'],

            ['name' => 'price_lists.view',            'display_name' => 'Visualizza listini',               'description' => 'Visualizza i listini (relazione tra fornitori e componenti).'],
            ['name' => 'price_lists.create',          'display_name' => 'Crea listini',                     'description' => 'Crea nuovi listini per la gestione prezzi.'],
            ['name' => 'price_lists.update',          'display_name' => 'Modifica listini',                 'description' => 'Modifica listini esistenti.'],
            ['name' => 'price_lists.delete',          'display_name' => 'Elimina listini',                  'description' => 'Elimina listini dal sistema.'],

            ['name' => 'product-prices.view',         'display_name' => 'Visualizza prezzi prodotto',       'description' => 'Visualizza i prezzi per prodotto.'],
            ['name' => 'product-prices.create',       'display_name' => 'Crea prezzi prodotto',           'description' => 'Crea nuovi prezzi per prodotto.'],
            ['name' => 'product-prices.update',       'display_name' => 'Modifica prezzi prodotto',       'description' => 'Modifica prezzi esistenti per prodotto.'],
            ['name' => 'product-prices.delete',       'display_name' => 'Elimina prezzi prodotto',       'description' => 'Elimina prezzi per prodotto dal sistema.'],

            ['name' => 'product-variables.view',      'display_name' => 'Visualizza variabili prodotto',    'description' => 'Visualizza le variabili associate ai prodotti.'],
            ['name' => 'product-variables.create',    'display_name' => 'Crea variabili prodotto',          'description' => 'Crea nuove variabili per i prodotti.'],
            ['name' => 'product-variables.update',    'display_name' => 'Modifica variabili prodotto',      'description' => 'Modifica variabili esistenti per i prodotti.'],
            ['name' => 'product-variables.manage',    'display_name' => 'Gestisci variabili prodotto',      'description' => 'Gestisce l\'associazione delle variabili ai prodotti.'],
            ['name' => 'product-variables.delete',    'display_name' => 'Elimina variabili prodotto',       'description' => 'Elimina variabili prodotto dal sistema.'],

            ['name' => 'reports.orders.customer',     'display_name' => 'Report ordini cliente',            'description' => 'Genera report storico degli ordini cliente.'],
            ['name' => 'reports.orders.supplier',     'display_name' => 'Report ordini fornitore',          'description' => 'Genera report storico degli ordini fornitore.'],
            ['name' => 'reports.stock_levels',        'display_name' => 'Report giacenze',                  'description' => 'Genera report delle giacenze attuali.'],
            ['name' => 'reports.stock_movements',     'display_name' => 'Report movimenti',                 'description' => 'Genera report storico delle movimentazioni di magazzino.'],
        ];

        foreach ($permissions as $perm) {
            // Crea o aggiorna il permesso con i campi aggiuntivi
            Permission::updateOrCreate(
                ['name' => $perm['name']],
                [
                    'display_name' => $perm['display_name'],
                    'description'  => $perm['description'],
                    'guard_name'   => 'web'
                ]
            );
        }

        // Ripulisce la cache di Spatie per applicare i nuovi permessi
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}