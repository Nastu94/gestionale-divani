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

/*
|--------------------------------------------------------------------------
| Rotte pubbliche
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
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

    /*
|--------------------------------------------------------------------------
    | Anagrafica Clienti
|--------------------------------------------------------------------------
    */
    Route::resource('customers', CustomerController::class)
        ->names([
            'index'   => 'customers.index',
            'create'  => 'customers.create',
            'store'   => 'customers.store',
            'show'    => 'customers.show',
            'edit'    => 'customers.edit',
            'update'  => 'customers.update',
            'destroy' => 'customers.destroy',
        ])
        ->middleware([
            'index'   => 'permission:customers.view',
            'create'  => 'permission:customers.create',
            'store'   => 'permission:customers.create',
            'show'    => 'permission:customers.view',
            'edit'    => 'permission:customers.update',
            'update'  => 'permission:customers.update',
            'destroy' => 'permission:customers.delete',
        ]);

    /*
|--------------------------------------------------------------------------
    | Anagrafica Fornitori
|--------------------------------------------------------------------------
    */
    Route::resource('suppliers', SupplierController::class)
        ->names([
            'index'   => 'suppliers.index',
            'create'  => 'suppliers.create',
            'store'   => 'suppliers.store',
            'show'    => 'suppliers.show',
            'edit'    => 'suppliers.edit',
            'update'  => 'suppliers.update',
            'destroy' => 'suppliers.destroy',
        ])
        ->middleware([
            'index'   => 'permission:suppliers.view',
            'create'  => 'permission:suppliers.create',
            'store'   => 'permission:suppliers.create',
            'show'    => 'permission:suppliers.view',
            'edit'    => 'permission:suppliers.update',
            'update'  => 'permission:suppliers.update',
            'destroy' => 'permission:suppliers.delete',
        ]);

    /*
|--------------------------------------------------------------------------
    | Componenti (Articoli)
|--------------------------------------------------------------------------
    */
    Route::resource('components', ComponentController::class)
        ->names([
            'index'   => 'components.index',
            'create'  => 'components.create',
            'store'   => 'components.store',
            'show'    => 'components.show',
            'edit'    => 'components.edit',
            'update'  => 'components.update',
            'destroy' => 'components.destroy',
        ])
        ->middleware([
            'index'   => 'permission:components.view',
            'create'  => 'permission:components.create',
            'store'   => 'permission:components.create',
            'show'    => 'permission:components.view',
            'edit'    => 'permission:components.update',
            'update'  => 'permission:components.update',
            'destroy' => 'permission:components.delete',
        ]);

    /*
|--------------------------------------------------------------------------
    | Prodotti (Modelli)
|--------------------------------------------------------------------------
    */
    Route::resource('products', ProductController::class)
        ->names([
            'index'   => 'products.index',
            'create'  => 'products.create',
            'store'   => 'products.store',
            'show'    => 'products.show',
            'edit'    => 'products.edit',
            'update'  => 'products.update',
            'destroy' => 'products.destroy',
        ])
        ->middleware([
            'index'   => 'permission:products.view',
            'create'  => 'permission:products.create',
            'store'   => 'permission:products.create',
            'show'    => 'permission:products.view',
            'edit'    => 'permission:products.update',
            'update'  => 'permission:products.update',
            'destroy' => 'permission:products.delete',
        ]);

    /*
|--------------------------------------------------------------------------
    | Ordini Cliente
|--------------------------------------------------------------------------
    */
    Route::resource('orders/customer', OrderCustomerController::class)
        ->parameters(['customer' => 'order'])
        ->names([
            'index'   => 'orders.customer.index',
            'create'  => 'orders.customer.create',
            'store'   => 'orders.customer.store',
            'show'    => 'orders.customer.show',
            'edit'    => 'orders.customer.edit',
            'update'  => 'orders.customer.update',
            'destroy' => 'orders.customer.destroy',
        ])
        ->middleware([
            'index'   => 'permission:orders.customer.view',
            'create'  => 'permission:orders.customer.create',
            'store'   => 'permission:orders.customer.create',
            'show'    => 'permission:orders.customer.view',
            'edit'    => 'permission:orders.customer.update',
            'update'  => 'permission:orders.customer.update',
            'destroy' => 'permission:orders.customer.delete',
        ]);

    /*
|--------------------------------------------------------------------------
    | Ordini Fornitore
|--------------------------------------------------------------------------
    */
    Route::resource('orders/supplier', OrderSupplierController::class)
        ->parameters(['supplier' => 'order'])
        ->names([
            'index'   => 'orders.supplier.index',
            'create'  => 'orders.supplier.create',
            'store'   => 'orders.supplier.store',
            'show'    => 'orders.supplier.show',
            'edit'    => 'orders.supplier.edit',
            'update'  => 'orders.supplier.update',
            'destroy' => 'orders.supplier.destroy',
        ])
        ->middleware([
            'index'   => 'permission:orders.supplier.view',
            'create'  => 'permission:orders.supplier.create',
            'store'   => 'permission:orders.supplier.create',
            'show'    => 'permission:orders.supplier.view',
            'edit'    => 'permission:orders.supplier.update',
            'update'  => 'permission:orders.supplier.update',
            'destroy' => 'permission:orders.supplier.delete',
        ]);

    /*
|--------------------------------------------------------------------------
    | Magazzino
|--------------------------------------------------------------------------
    */
    Route::get('stock-levels', [StockLevelController::class, 'index'])
        ->name('stock-levels.index')
        ->middleware('permission:stock.view');
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
    | Alert
|--------------------------------------------------------------------------
    */
    Route::resource('alerts', AlertController::class)
        ->names([
            'index'   => 'alerts.index',
            'create'  => 'alerts.create',
            'store'   => 'alerts.store',
            'edit'    => 'alerts.edit',
            'update'  => 'alerts.update',
            'destroy' => 'alerts.destroy',
        ])
        ->middleware([
            'index'   => 'permission:alerts.view',
            'create'  => 'permission:alerts.create',
            'store'   => 'permission:alerts.create',
            'edit'    => 'permission:alerts.update',
            'update'  => 'permission:alerts.update',
            'destroy' => 'permission:alerts.delete',
        ]);

    /*
|--------------------------------------------------------------------------
    | Listini Prezzi (Pivot ComponentSupplier)
|--------------------------------------------------------------------------
    */
    Route::resource('price-lists', PriceListController::class)
        ->names([
            'index'   => 'price_lists.index',
            'create'  => 'price_lists.create',
            'store'   => 'price_lists.store',
            'show'    => 'price_lists.show',
            'edit'    => 'price_lists.edit',
            'update'  => 'price_lists.update',
            'destroy' => 'price_lists.destroy',
        ])
        ->middleware([
            'index'   => 'permission:price_lists.view',
            'create'  => 'permission:price_lists.create',
            'store'   => 'permission:price_lists.create',
            'edit'    => 'permission:price_lists.update',
            'update'  => 'permission:price_lists.update',
            'destroy' => 'permission:price_lists.delete',
        ]);

    /*
|--------------------------------------------------------------------------
    | Reportistica
|--------------------------------------------------------------------------
    */
    Route::get('reports/orders/customer', [ReportOrderCustomerController::class, 'index'])
        ->name('reports.orders.customer')
        ->middleware('permission:reports.orders.customer');
    Route::get('reports/orders/supplier', [ReportOrderSupplierController::class, 'index'])
        ->name('reports.orders.supplier')
        ->middleware('permission:reports.orders.supplier');
    Route::get('reports/stock-levels', [ReportStockLevelsController::class, 'index'])
        ->name('reports.stock_levels')
        ->middleware('permission:reports.stock_levels');
    Route::get('reports/stock-movements', [ReportStockMovementsController::class, 'index'])
        ->name('reports.stock_movements')
        ->middleware('permission:reports.stock_movements');

    /*
|--------------------------------------------------------------------------
    | ACL: Utenti, Ruoli e Permessi
|--------------------------------------------------------------------------
    */
    Route::resource('users', UserController::class)
        ->names([
            'index'   => 'users.index',
            'create'  => 'users.create',
            'store'   => 'users.store',
            'show'    => 'users.show',
            'edit'    => 'users.edit',
            'update'  => 'users.update',
            'destroy' => 'users.destroy',
        ])
        ->middleware([
            'index'   => 'permission:users.view',
            'create'  => 'permission:users.create',
            'store'   => 'permission:users.create',
            'show'    => 'permission:users.view',
            'edit'    => 'permission:users.update',
            'update'  => 'permission:users.update',
            'destroy' => 'permission:users.delete',
        ]);
    Route::resource('roles', RoleController::class)
        ->names([
            'index'   => 'roles.index',
            'create'  => 'roles.create',
            'store'   => 'roles.store',
            'show'    => 'roles.show',
            'edit'    => 'roles.edit',
            'update'  => 'roles.update',
            'destroy' => 'roles.destroy',
        ])
        ->middleware('permission:roles.manage');
    Route::resource('permissions', PermissionController::class)
        ->names([
            'index'   => 'permissions.index',
            'create'  => 'permissions.create',
            'store'   => 'permissions.store',
            'show'    => 'permissions.show',
            'edit'    => 'permissions.edit',
            'update'  => 'permissions.update',
            'destroy' => 'permissions.destroy',
        ])
        ->middleware('permission:roles.manage');

});