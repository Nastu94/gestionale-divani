<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\ComponentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderCustomerController;
use App\Http\Controllers\OrderSupplierController;
use App\Http\Controllers\StockLevelController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ReportOrderCustomerController;
use App\Http\Controllers\ReportOrderSupplierController;
use App\Http\Controllers\ReportStockLevelsController;
use App\Http\Controllers\ReportStockMovementsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WarehouseController;

/*
|--------------------------------------------------------------------------
| Rotte pubbliche
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('auth.login');
});

/*
|--------------------------------------------------------------------------
| Rotte protette (Jetstream + Sanctum + Verified)
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    // Dashboard principale
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Global Search
    Route::get('/search', [SearchController::class, '__invoke'])
        ->name('search');

    /*
|--------------------------------------------------------------------------
    | Anagrafica Clienti
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Clienti - solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Qui raggruppiamo le rotte per la visualizzazione della
    | lista e del dettaglio cliente, protette dal permesso
    | customers.view.
    |
    */
    Route::resource('customers', CustomerController::class)
        ->only(['index', 'show'])
        ->names([
            'index' => 'customers.index',
            'show'  => 'customers.show',
        ])
        ->middleware('permission:customers.view');

    /*
    |--------------------------------------------------------------------------
    | Gestione Clienti - solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Queste rotte servono per mostrare il form di creazione
    | e salvare il nuovo cliente, protette dal permesso
    | customers.create.
    |
    */
    Route::resource('customers', CustomerController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'customers.create',
            'store'  => 'customers.store',
        ])
        ->middleware('permission:customers.create');

    /*
    |--------------------------------------------------------------------------
    | Gestione Clienti - solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Queste rotte permettono di mostrare il form di modifica
    | e aggiornare i dati del cliente esistente, protette dal
    | permesso customers.update.
    |
    */
    Route::resource('customers', CustomerController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'customers.edit',
            'update' => 'customers.update',
        ])
        ->middleware('permission:customers.update');

    /*
    |--------------------------------------------------------------------------
    | Gestione Clienti - solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Questa rotta gestisce la cancellazione di un cliente,
    | protetta dal permesso customers.delete.
    |
    */
    Route::resource('customers', CustomerController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'customers.destroy',
        ])
        ->middleware('permission:customers.delete');

    /*
|--------------------------------------------------------------------------
    | Anagrafica Fornitori
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Fornitori – solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Queste rotte mostrano la lista dei fornitori e il dettaglio di uno
    | specifico fornitore. Protette dal permesso suppliers.view.
    |
    */
    Route::resource('suppliers', SupplierController::class)
        ->only(['index', 'show'])
        ->names([
            'index' => 'suppliers.index',
            'show'  => 'suppliers.show',
        ])
        ->middleware('permission:suppliers.view');

    /*
    |--------------------------------------------------------------------------
    | Gestione Fornitori – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Queste rotte mostrano il form per creare un nuovo fornitore e
    | gestiscono il salvataggio. Protette dal permesso suppliers.create.
    |
    */
    Route::resource('suppliers', SupplierController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'suppliers.create',
            'store'  => 'suppliers.store',
        ])
        ->middleware('permission:suppliers.create');

    /*
    |--------------------------------------------------------------------------
    | Gestione Fornitori – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Queste rotte mostrano il form per modificare un fornitore esistente
    | e gestiscono l’aggiornamento. Protette dal permesso suppliers.update.
    |
    */
    Route::resource('suppliers', SupplierController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'suppliers.edit',
            'update' => 'suppliers.update',
        ])
        ->middleware('permission:suppliers.update');

    /*
    |--------------------------------------------------------------------------
    | Gestione Fornitori – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Questa rotta gestisce la cancellazione di un fornitore dal sistema.
    | Protetta dal permesso suppliers.delete.
    |
    */
    Route::resource('suppliers', SupplierController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'suppliers.destroy',
        ])
        ->middleware('permission:suppliers.delete');

    /*
|--------------------------------------------------------------------------
    | Componenti (Articoli)
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Componenti – solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Qui definiamo le rotte per mostrare la lista dei componenti
    | e il dettaglio di un singolo componente, protette dal permesso
    | components.view.
    |
    */
    Route::resource('components', ComponentController::class)
        ->only(['index', 'show'])
        ->names([
            'index' => 'components.index',
            'show'  => 'components.show',
        ])
        ->middleware('permission:components.view');

    /*
    |--------------------------------------------------------------------------
    | Gestione Componenti – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Queste rotte servono per visualizzare il form di creazione
    | di un nuovo componente e per salvarlo, protette dal permesso
    | components.create.
    |
    */
    Route::resource('components', ComponentController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'components.create',
            'store'  => 'components.store',
        ])
        ->middleware('permission:components.create');

    /*
    |--------------------------------------------------------------------------
    | Gestione Componenti – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Qui definiamo le rotte per mostrare il form di modifica
    | di un componente esistente e per aggiornarlo,
    | protette dal permesso components.update.
    |
    */
    Route::resource('components', ComponentController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'components.edit',
            'update' => 'components.update',
        ])
        ->middleware('permission:components.update');

    /*
    |--------------------------------------------------------------------------
    | Gestione Componenti – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Questa rotta si occupa della cancellazione di un componente,
    | protetta dal permesso components.delete.
    |
    */
    Route::resource('components', ComponentController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'components.destroy',
        ])
        ->middleware('permission:components.delete');

    /*
|--------------------------------------------------------------------------
    | Prodotti (Modelli)
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Prodotti – solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Mostra la lista dei prodotti e il dettaglio di un singolo prodotto.
    | Protette dal permesso products.view.
    |
    */
    Route::resource('products', ProductController::class)
        ->only(['index', 'show'])
        ->names([
            'index' => 'products.index',
            'show'  => 'products.show',
        ])
        ->middleware('permission:products.view');

    /*
    |--------------------------------------------------------------------------
    | Gestione Prodotti – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per creare un nuovo prodotto e gestisce il salvataggio.
    | Protette dal permesso products.create.
    |
    */
    Route::resource('products', ProductController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'products.create',
            'store'  => 'products.store',
        ])
        ->middleware('permission:products.create');

    /*
    |--------------------------------------------------------------------------
    | Gestione Prodotti – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per modificare un prodotto esistente e gestisce l'aggiornamento.
    | Protette dal permesso products.update.
    |
    */
    Route::resource('products', ProductController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'products.edit',
            'update' => 'products.update',
        ])
        ->middleware('permission:products.update');

    /*
    |--------------------------------------------------------------------------
    | Gestione Prodotti – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Gestisce la cancellazione di un prodotto dal sistema.
    | Protetta dal permesso products.delete.
    |
    */
    Route::resource('products', ProductController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'products.destroy',
        ])
        ->middleware('permission:products.delete');

    /*
|--------------------------------------------------------------------------
    | Ordini Cliente
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Ordini Cliente – solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Mostra la lista degli ordini cliente e il dettaglio di un singolo ordine.
    | Protette dal permesso orders.customer.view.
    |
    */
    Route::resource('orders/customer', OrderCustomerController::class)
        ->parameters(['customer' => 'order'])
        ->only(['index', 'show'])
        ->names([
            'index' => 'orders.customer.index',
            'show'  => 'orders.customer.show',
        ])
        ->middleware('permission:orders.customer.view');

    /*
    |--------------------------------------------------------------------------
    | Gestione Ordini Cliente – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per creare un nuovo ordine cliente e gestisce il salvataggio.
    | Protette dal permesso orders.customer.create.
    |
    */
    Route::resource('orders/customer', OrderCustomerController::class)
        ->parameters(['customer' => 'order'])
        ->only(['create', 'store'])
        ->names([
            'create' => 'orders.customer.create',
            'store'  => 'orders.customer.store',
        ])
        ->middleware('permission:orders.customer.create');

    /*
    |--------------------------------------------------------------------------
    | Gestione Ordini Cliente – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per modificare un ordine cliente esistente e gestisce l'aggiornamento.
    | Protette dal permesso orders.customer.update.
    |
    */
    Route::resource('orders/customer', OrderCustomerController::class)
        ->parameters(['customer' => 'order'])
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'orders.customer.edit',
            'update' => 'orders.customer.update',
        ])
        ->middleware('permission:orders.customer.update');

    /*
    |--------------------------------------------------------------------------
    | Gestione Ordini Cliente – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Gestisce la cancellazione di un ordine cliente dal sistema.
    | Protetta dal permesso orders.customer.delete.
    |
    */
    Route::resource('orders/customer', OrderCustomerController::class)
        ->parameters(['customer' => 'order'])
        ->only(['destroy'])
        ->names([
            'destroy' => 'orders.customer.destroy',
        ])
        ->middleware('permission:orders.customer.delete');

    /*
|--------------------------------------------------------------------------
    | Ordini Fornitore
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Ordini Fornitore – solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Mostra la lista degli ordini fornitore e il dettaglio di un singolo ordine.
    | Protette dal permesso orders.supplier.view.
    |
    */
    Route::resource('orders/supplier', OrderSupplierController::class)
        ->parameters(['supplier' => 'order'])
        ->only(['index', 'show'])
        ->names([
            'index' => 'orders.supplier.index',
            'show'  => 'orders.supplier.show',
        ])
        ->middleware('permission:orders.supplier.view');

    /*
    |--------------------------------------------------------------------------
    | Gestione Ordini Fornitore – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per creare un nuovo ordine fornitore e gestisce il salvataggio.
    | Protette dal permesso orders.supplier.create.
    |
    */
    Route::resource('orders/supplier', OrderSupplierController::class)
        ->parameters(['supplier' => 'order'])
        ->only(['create', 'store'])
        ->names([
            'create' => 'orders.supplier.create',
            'store'  => 'orders.supplier.store',
        ])
        ->middleware('permission:orders.supplier.create');

    /*
    |--------------------------------------------------------------------------
    | Gestione Ordini Fornitore – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per modificare un ordine fornitore esistente e gestisce l'aggiornamento.
    | Protette dal permesso orders.supplier.update.
    |
    */
    Route::resource('orders/supplier', OrderSupplierController::class)
        ->parameters(['supplier' => 'order'])
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'orders.supplier.edit',
            'update' => 'orders.supplier.update',
        ])
        ->middleware('permission:orders.supplier.update');

    /*
    |--------------------------------------------------------------------------
    | Gestione Ordini Fornitore – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Gestisce la cancellazione di un ordine fornitore dal sistema.
    | Protetta dal permesso orders.supplier.delete.
    |
    */
    Route::resource('orders/supplier', OrderSupplierController::class)
        ->parameters(['supplier' => 'order'])
        ->only(['destroy'])
        ->names([
            'destroy' => 'orders.supplier.destroy',
        ])
        ->middleware('permission:orders.supplier.delete');

    /*
|--------------------------------------------------------------------------
    | Magazzino
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Magazzino – visualizzione giacenze
    |--------------------------------------------------------------------------
    |
    | Mostra la lista delle giacenze
    | Protette dal permesso stock.view.
    |
    */
    Route::get('stock-levels', [StockLevelController::class, 'index'])
        ->name('stock-levels.index')
        ->middleware('permission:stock.view');
    /*
    |--------------------------------------------------------------------------
    | Gestione Magazzino – solo index, show e store (movimenti di magazzino)
    |--------------------------------------------------------------------------
    |
    | Permette di visualizzare i movimenti di magazzino,
    | registrare nuove entrate e visualizzare i dettagli di un movimento.
    | Protette dal permesso stock.view e stock.entry.
    |
    */
    Route::resource('stock-movements', StockMovementController::class)
        ->only(['index','store','show'])
        ->names([
            'index' => 'stock-movements.index',
            'store' => 'stock-movements.store',
            'show'  => 'stock-movements.show',
        ])
        ->middleware([
            'index' => 'permission:stock.view',
            'store' => 'permission:stock.entry',
        ]);
    
    /*
    |--------------------------------------------------------------------------
    | Gestione Magazzino – solo index, show (lista di magazzini)
    |--------------------------------------------------------------------------
    |
    | Permette di visualizzare i magazzini.
    | Protette dal permesso warehouse.view.
    |
    */
    Route::resource('warehouses', WarehouseController::class)
        ->only(['index', 'show'])
        ->names([
            'index' => 'warehouses.index',
            'show'  => 'warehouses.show',
        ])
        ->middleware('permission:warehouses.view');
    /*
    |--------------------------------------------------------------------------
    | Gestione Magazzino – solo craete e store (lista di magazzini)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per creare un nuovo magazzino e gestisce il salvataggio.
    | Protetta dal permesso warehouse.create.
    |
    */
    Route::resource('warehouses', WarehouseController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'warehouses.create',
            'store'  => 'warehouses.store',
        ])
        ->middleware('permission:warehouses.create');
    /*
    |--------------------------------------------------------------------------
    | Gestione Magazzino – solo edite e update (lista di magazzini)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per modificare un magazzino esistente e gestisce l'aggiornamento.
    | Protetta dal permesso warehouse.update.
    |
    */
    Route::resource('warehouses', WarehouseController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'warehouses.edit',
            'update' => 'warehouses.update',
        ])
        ->middleware('permission:warehouses.update');
    /*
    |--------------------------------------------------------------------------
    | Gestione Magazzino – solo delete (movimenti di magazzino)
    |--------------------------------------------------------------------------
    |
    | Gestisce la cancellazione di un magazzino dal sistema.
    | Protetta dal permesso warehouse.delete.
    |
    */
    Route::resource('warehouses', WarehouseController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'warehouses.destroy',
        ])
        ->middleware('permission:warehouses.delete');
    /*
|--------------------------------------------------------------------------
    | Alert
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Alert – solo index (visualizzazione elenco)
    |--------------------------------------------------------------------------
    |
    | Mostra la lista delle segnalazioni/avvisi.
    | Protetta dal permesso alerts.view.
    |
    */
    Route::resource('alerts', AlertController::class)
        ->only(['index'])
        ->names([
            'index' => 'alerts.index',
        ])
        ->middleware('permission:alerts.view');

    /*
    |--------------------------------------------------------------------------
    | Gestione Alert – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per creare un nuovo avviso e gestisce il salvataggio.
    | Protette dal permesso alerts.create.
    |
    */
    Route::resource('alerts', AlertController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'alerts.create',
            'store'  => 'alerts.store',
        ])
        ->middleware('permission:alerts.create');

    /*
    |--------------------------------------------------------------------------
    | Gestione Alert – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per modificare un avviso esistente e gestisce l'aggiornamento.
    | Protette dal permesso alerts.update.
    |
    */
    Route::resource('alerts', AlertController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'alerts.edit',
            'update' => 'alerts.update',
        ])
        ->middleware('permission:alerts.update');

    /*
    |--------------------------------------------------------------------------
    | Gestione Alert – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Gestisce la cancellazione di un avviso dal sistema.
    | Protetta dal permesso alerts.delete.
    |
    */
    Route::resource('alerts', AlertController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'alerts.destroy',
        ])
        ->middleware('permission:alerts.delete');

    /*
|--------------------------------------------------------------------------
    | Listini Prezzi (Pivot ComponentSupplier)
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Liste Prezzi – solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Mostra la lista delle liste prezzi e il dettaglio di una specifica lista.
    | Protette dal permesso price_lists.view.
    |
    */
    Route::resource('price-lists', PriceListController::class)
        ->only(['index', 'show'])
        ->names([
            'index' => 'price_lists.index',
            'show'  => 'price_lists.show',
        ])
        ->middleware('permission:price_lists.view');

    /*
    |--------------------------------------------------------------------------
    | Gestione Liste Prezzi – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per creare una nuova lista prezzi e gestisce il salvataggio.
    | Protette dal permesso price_lists.create.
    |
    */
    Route::resource('price-lists', PriceListController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'price_lists.create',
            'store'  => 'price_lists.store',
        ])
        ->middleware('permission:price_lists.create');

    /*
    |--------------------------------------------------------------------------
    | Gestione Liste Prezzi – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per modificare una lista prezzi esistente e gestisce l'aggiornamento.
    | Protette dal permesso price_lists.update.
    |
    */
    Route::resource('price-lists', PriceListController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'price_lists.edit',
            'update' => 'price_lists.update',
        ])
        ->middleware('permission:price_lists.update');

    /*
    |--------------------------------------------------------------------------
    | Gestione Liste Prezzi – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Gestisce la cancellazione di una lista prezzi dal sistema.
    | Protetta dal permesso price_lists.delete.
    |
    */
    Route::resource('price-lists', PriceListController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'price_lists.destroy',
        ])
        ->middleware('permission:price_lists.delete');

    /*
|--------------------------------------------------------------------------
    | Reportistica
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Report Ordini Cliente
    |--------------------------------------------------------------------------
    |
    | Mostra il report degli ordini cliente, con aggregazioni e filtri.
    | Protetta dal permesso reports.orders.customer.
    |
    */
    Route::get('reports/orders/customer', [ReportOrderCustomerController::class, 'index'])
        ->name('reports.orders.customer')
        ->middleware('permission:reports.orders.customer');

    /*
    |--------------------------------------------------------------------------
    | Report Ordini Fornitore
    |--------------------------------------------------------------------------
    |
    | Mostra il report degli ordini fornitore, con aggregazioni e filtri.
    | Protetta dal permesso reports.orders.supplier.
    |
    */
    Route::get('reports/orders/supplier', [ReportOrderSupplierController::class, 'index'])
        ->name('reports.orders.supplier')
        ->middleware('permission:reports.orders.supplier');

    /*
    |--------------------------------------------------------------------------
    | Report Livelli di Magazzino
    |--------------------------------------------------------------------------
    |
    | Mostra il report dei livelli di stock attuali per ciascun prodotto/componente.
    | Protetta dal permesso reports.stock_levels.
    |
    */
    Route::get('reports/stock-levels', [ReportStockLevelsController::class, 'index'])
        ->name('reports.stock_levels')
        ->middleware('permission:reports.stock_levels');

    /*
    |--------------------------------------------------------------------------
    | Report Movimenti di Magazzino
    |--------------------------------------------------------------------------
    |
    | Mostra il report dei movimenti di stock, includendo entrate e uscite.
    | Protetta dal permesso reports.stock_movements.
    |
    */
    Route::get('reports/stock-movements', [ReportStockMovementsController::class, 'index'])
        ->name('reports.stock_movements')
        ->middleware('permission:reports.stock_movements');

    /*
|--------------------------------------------------------------------------
    | ACL: Utenti, Ruoli e Permessi
|--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    | Gestione Utenti – solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Mostra la lista di tutti gli utenti e il dettaglio di un singolo utente.
    | Protette dal permesso users.view.
    |
    */
    Route::resource('users', UserController::class)
        ->only(['index', 'show'])
        ->names([
            'index' => 'users.index',
            'show'  => 'users.show',
        ])
        ->middleware(['permission:users.view']);

    /*
    |--------------------------------------------------------------------------
    | Gestione Utenti – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per creare un nuovo utente e salva i dati inviati.
    | Protette dal permesso users.create.
    |
    */
    Route::resource('users', UserController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'users.create',
            'store'  => 'users.store',
        ])
        ->middleware(['permission:users.create']);

    /*
    |--------------------------------------------------------------------------
    | Gestione Utenti – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per modificare un utente esistente e aggiorna i dati.
    | Protette dai permessi users.edit e users.update.
    |
    */
    Route::resource('users', UserController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'users.edit',
            'update' => 'users.update',
        ])
        ->middleware(['permission:users.update']);

    /*
    |--------------------------------------------------------------------------
    | Gestione Utenti – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Gestisce la rimozione di un utente dal sistema.
    | Protetta dal permesso users.delete.
    |
    */
    Route::resource('users', UserController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'users.destroy',
        ])
        ->middleware(['permission:users.delete']);

    /*
    |--------------------------------------------------------------------------
    | Gestione Ruoli – solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Mostra la lista dei ruoli e il dettaglio di un singolo ruolo.
    | Protette dal permesso roles.manage.
    |
    */
    Route::resource('roles', RoleController::class)
        ->only(['index', 'show'])
        ->names([
            'index' => 'roles.index',
            'show'  => 'roles.show',
        ])
        ->middleware('permission:roles.manage');

    /*
    |--------------------------------------------------------------------------
    | Gestione Ruoli – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per creare un nuovo ruolo e gestisce il salvataggio.
    | Protette dal permesso roles.manage.
    |
    */
    Route::resource('roles', RoleController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'roles.create',
            'store'  => 'roles.store',
        ])
        ->middleware('permission:roles.manage');

    /*
    |--------------------------------------------------------------------------
    | Gestione Ruoli – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per modificare un ruolo esistente e gestisce l'aggiornamento.
    | Protette dal permesso roles.manage.
    |
    */
    Route::resource('roles', RoleController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'roles.edit',
            'update' => 'roles.update',
        ])
        ->middleware('permission:roles.manage');

    /*
    |--------------------------------------------------------------------------
    | Gestione Ruoli – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Gestisce la cancellazione di un ruolo dal sistema.
    | Protetta dal permesso roles.manage.
    |
    */
    Route::resource('roles', RoleController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'roles.destroy',
        ])
        ->middleware('permission:roles.manage');

    /*
    |--------------------------------------------------------------------------
    | Gestione Permessi – solo index & show (visualizzazione)
    |--------------------------------------------------------------------------
    |
    | Mostra la lista dei permessi e il dettaglio di un singolo permesso.
    | Protette dal permesso roles.manage.
    |
    */
    Route::resource('permissions', PermissionController::class)
        ->only(['index', 'show'])
        ->names([
            'index' => 'permissions.index',
            'show'  => 'permissions.show',
        ])
        ->middleware('permission:roles.manage');

    /*
    |--------------------------------------------------------------------------
    | Gestione Permessi – solo create & store (creazione)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per creare un nuovo permesso e gestisce il salvataggio.
    | Protette dal permesso roles.manage.
    |
    */
    Route::resource('permissions', PermissionController::class)
        ->only(['create', 'store'])
        ->names([
            'create' => 'permissions.create',
            'store'  => 'permissions.store',
        ])
        ->middleware('permission:roles.manage');

    /*
    |--------------------------------------------------------------------------
    | Gestione Permessi – solo edit & update (modifica)
    |--------------------------------------------------------------------------
    |
    | Visualizza il form per modificare un permesso esistente e gestisce l'aggiornamento.
    | Protette dal permesso roles.manage.
    |
    */
    Route::resource('permissions', PermissionController::class)
        ->only(['edit', 'update'])
        ->names([
            'edit'   => 'permissions.edit',
            'update' => 'permissions.update',
        ])
        ->middleware('permission:roles.manage');

    /*
    |--------------------------------------------------------------------------
    | Gestione Permessi – solo destroy (cancellazione)
    |--------------------------------------------------------------------------
    |
    | Gestisce la cancellazione di un permesso dal sistema.
    | Protetta dal permesso roles.manage.
    |
    */
    Route::resource('permissions', PermissionController::class)
        ->only(['destroy'])
        ->names([
            'destroy' => 'permissions.destroy',
        ])
        ->middleware('permission:roles.manage');

});