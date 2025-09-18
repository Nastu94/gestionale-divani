<?php

return [
    /*--------------------------------------------------------------------------
    | Sidebar (testo solo, senza icone)
    |--------------------------------------------------------------------------
    | Le stesse sezioni e sottosezioni di 'grid_menu', ma qui senza icone.
    */
    'sidebar' => [
        [
            'section' => 'Anagrafiche',
            'items'   => [
                ['label'=>'Categorie',  'route'=>'categories.index',       'permission'=>'categories.view'],
                ['label'=>'Componenti', 'route'=>'components.index',       'permission'=>'components.view'],
                ['label'=>'Clienti',    'route'=>'customers.index',        'permission'=>'customers.view'],
                ['label'=>'Fornitori',  'route'=>'suppliers.index',        'permission'=>'suppliers.view'],
                ['label'=>'Prodotti',   'route'=>'products.index',         'permission'=>'products.view'],
                ['label'=>'Variabili',  'route'=>'variables.index',        'permission'=>'product-variables.view'],
            ],
        ],
        [
            'section' => 'Ordini',
            'items'   => [
                ['label'=>'Ordini Cliente',   'route'=>'orders.customer.index',  'permission'=>'orders.customer.view'],
                ['label'=>'Ordini Fornitore', 'route'=>'orders.supplier.index',  'permission'=>'orders.supplier.view'],
                ['label'=>'Dashboard Supply', 'route'=>'orders.supply.dashboard', 'permission'=>'orders.supplier.manage_supply'],
            ],
        ],
        [
            'section' => 'Magazzino',
            'items'   => [
                ['label'=>'Gestione',      'route'=>'warehouses.index',              'permission'=>'warehouses.view'],
                ['label'=>'Giacenze',      'route'=>'stock-levels.index',            'permission'=>'stock.view'],
                ['label'=>'Entrate',       'route'=>'stock-movements-entry.index',   'permission'=>'stock.entry'],
                ['label'=>'Uscite',        'route'=>'stock-movements-exit.index',    'permission'=>'stock.exit'],
                ['label'=>'Alert',         'route'=>'alerts.index',                  'permission'=>'alerts.view'],
            ],
        ],
        [
            'section' => 'Report',
            'items'   => [
                ['label'=>'Ordini Cliente',    'route'=>'reports.orders.customer',  'permission'=>'reports.orders.customer'],
                ['label'=>'Ordini Fornitore',  'route'=>'reports.orders.supplier',  'permission'=>'reports.orders.supplier'],
                ['label'=>'Giacenze',          'route'=>'reports.stock_levels',     'permission'=>'reports.stock_levels'],
                ['label'=>'Movimentazioni',    'route'=>'reports.stock_movements',  'permission'=>'reports.stock_movements'],
            ],
        ],
        [
            'section' => 'ACL',
            'items'   => [
                ['label'=>'Utenti',   'route'=>'users.index',        'permission'=>'users.view'],
                ['label'=>'Ruoli',    'route'=>'roles.index',        'permission'=>'roles.manage'],
            ],
        ],
    ],

    /*--------------------------------------------------------------------------
    | Grid Menu (menu a griglia gerarchico con icone per sezione e sottosezione)
    |--------------------------------------------------------------------------
    | Al click su una sezione, mostra i relativi items.
    */
    'grid_menu' => [
        [
            'section' => 'Anagrafiche',
            'icon'    => 'fa-address-book',
            'items'   => [
                ['label'=>'Componenti', 'route'=>'components.index',       'icon'=>'fa-cogs',                 'permission'=>'components.view'],
                ['label'=>'Clienti',    'route'=>'customers.index',        'icon'=>'fa-user',                 'permission'=>'customers.view'],
                ['label'=>'Fornitori',  'route'=>'suppliers.index',        'icon'=>'fa-truck',                'permission'=>'suppliers.view'],
                ['label'=>'Prodotti',   'route'=>'products.index',         'icon'=>'fa-box-open',             'permission'=>'products.view'],
                ['label'=>'Categorie',  'route'=>'categories.index',       'icon'=>'fa-sitemap',              'permission'=>'categories.view'],
                ['label'=>'Variabili',  'route'=>'variables.index',        'icon'=>'fa-palette',              'permission'=>'product-variables.view'],
            ],
        ],
        [
            'section' => 'Ordini',
            'icon'    => 'fa-shopping-cart',
            'items'   => [
                ['label'=>'Ordini Cliente',   'route'=>'orders.customer.index', 'icon'=>'fa-shopping-basket',     'permission'=>'orders.customer.view'],
                ['label'=>'Ordini Fornitore', 'route'=>'orders.supplier.index', 'icon'=>'fa-people-carry',        'permission'=>'orders.supplier.view'],
                ['label'=>'Dashboard Supply', 'route'=>'orders.supply.dashboard', 'icon'=>'fa-tachometer-alt',    'permission'=>'orders.supplier.manage_supply'],
            ],
        ],
        [
            'section' => 'Magazzino',
            'icon'    => 'fa-warehouse',
            'items'   => [
                ['label'=>'Gestione',      'route'=>'warehouses.index',              'icon'=>'fa-boxes-stacked',       'permission'=>'warehouses.view'],
                ['label'=>'Giacenze',      'route'=>'stock-levels.index',            'icon'=>'fa-layer-group',         'permission'=>'stock.view'],
                ['label'=>'Entrate',       'route'=>'stock-movements-entry.index',   'icon'=>'fas fa-download',        'permission'=>'stock.entry'],
                ['label'=>'Uscite',        'route'=>'stock-movements-exit.index',    'icon'=>'fas fa-upload',          'permission'=>'stock.exit'],
                ['label'=>'Alert',         'route'=>'alerts.index',                  'icon'=>'fa-bell',                'permission'=>'alerts.view'],
            ],
        ],
        [
            'section' => 'Report',
            'icon'    => 'fa-chart-line',
            'items'   => [
                ['label'=>'Ordini Cliente',   'route'=>'reports.orders.customer', 'icon'=>'fa-laptop',              'permission'=>'reports.orders.customer'],
                ['label'=>'Ordini Fornitore', 'route'=>'reports.orders.supplier', 'icon'=>'fa-desktop',             'permission'=>'reports.orders.supplier'],
                ['label'=>'Giacenze',         'route'=>'reports.stock_levels',    'icon'=>'fa-clipboard-list',      'permission'=>'reports.stock_levels'],
                ['label'=>'Movimentazioni',   'route'=>'reports.stock_movements', 'icon'=>'fa-sync-alt',            'permission'=>'reports.stock_movements'],
            ],
        ],
        [
            'section' => 'ACL',
            'icon'    => 'fa-user-shield',
            'items'   => [
                ['label'=>'Utenti',   'route'=>'users.index',        'icon'=>'fa-users-cog',           'permission'=>'users.view'],
                ['label'=>'Ruoli',    'route'=>'roles.index',        'icon'=>'fa-user-tag',            'permission'=>'roles.manage'],
            ],
        ],
    ],

    /*--------------------------------------------------------------------------
    | Dashboard Tiles
    |--------------------------------------------------------------------------
    | Widget aggiuntivi separati dal grid_menu.
    */
    'dashboard_tiles' => [
        ['label'=>'Clienti',           'route'=>'customers.index',       'icon'=>'fa-users',                 'permission'=>'customers.view',            'badge_count'=> "customers"],
        ['label'=>'Ordini Cliente',    'route'=>'orders.customer.index', 'icon'=>'fa-shopping-bag',          'permission'=>'orders.customer.view',      'badge_count'=> "orders_customer"],
        ['label'=>'Alert Critici',     'route'=>'alerts.index',          'icon'=>'fa-exclamation-triangle',  'permission'=>'alerts.view',               'badge_count'=> "alerts_critical"],
        ['label'=>'Sotto Soglia',      'route'=>'alerts.index',          'icon'=>'fa-exclamation-circle',    'permission'=>'alerts.view',               'badge_count'=> "alerts_low"],
    ],
];