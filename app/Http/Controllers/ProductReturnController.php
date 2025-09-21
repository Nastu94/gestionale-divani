<?php
/**
 * Controller: ProductReturnController@index
 *
 * Elenco resi con:
 *  - Autorizzazione: 'orders.customer.returns_manage'
 *  - Filtri per-colonna stile <x-th-menu>:
 *      • filter[number]   → LIKE su product_returns.number
 *      • filter[customer] → LIKE su customers.company (join on demand)
 *  - Sorting sicuro (whitelist):
 *      • number        → product_returns.number
 *      • return_date   → product_returns.return_date
 *      • customer      → customers.company (join on demand)
 *  - Stato (solo amministrativo / in magazzino) via withCount delle righe restock=true
 *  - Paginazione con query string preservata
 *  - Iniezione dataset per il modale: customers, products, fabrics, colors, warehouses, returnWarehouseId
 *
 * PHP 8.4 / Laravel 12 — Commenti secondo convenzioni del progetto.
 */

namespace App\Http\Controllers;

use App\Models\ProductReturn;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Fabric;
use App\Models\Color;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductReturnController extends Controller
{
    /**
     * Elenco resi con filtri/ordinamento e dataset per il modale.
     */
    public function index(Request $request)
    {
        // ───────────────────────────────── Autorizzazione ─────────────────────────────────
        if (Gate::denies('orders.customer.returns_manage')) {
            abort(403);
        }

        // ───────────────────────────────── Parametri UI (filtri + sort) ─────────────────────────────────
        $filters = (array) $request->query('filter', []);
        $filters = [
            'number'   => isset($filters['number'])   ? trim((string) $filters['number'])   : '',
            'customer' => isset($filters['customer']) ? trim((string) $filters['customer']) : '',
        ];

        $sort = (string) $request->query('sort', 'return_date');
        $dir  = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Campi ordinabili → colonne SQL
        $sortable = [
            'number'      => 'product_returns.number',
            'return_date' => 'product_returns.return_date',
            'customer'    => 'customers.company',
        ];

        if (! array_key_exists($sort, $sortable)) {
            $sort = 'return_date';
        }

        // ───────────────────────────────── Query base ─────────────────────────────────
        $q = ProductReturn::query()
            ->select('product_returns.*')
            ->with(['customer']) // per la stampa del cliente in tabella
            ->withCount(['lines as restock_lines_count' => function ($qq) {
                $qq->where('restock', true);
            }]);

        $needsCustomerJoin = ($filters['customer'] !== '') || ($sort === 'customer');

        if ($needsCustomerJoin) {
            $q->leftJoin('customers', 'customers.id', '=', 'product_returns.customer_id');
        }

        // ───────────────────────────────── Filtri per-colonna ─────────────────────────────────
        if ($filters['number'] !== '') {
            // LIKE su number con escape minimo del simbolo %
            $q->where('product_returns.number', 'like', '%' . str_replace('%', '\%', $filters['number']) . '%');
        }

        if ($filters['customer'] !== '') {
            // LIKE su customers.company (join già presente se necessario)
            $q->where('customers.company', 'like', '%' . str_replace('%', '\%', $filters['customer']) . '%');
        }

        // ───────────────────────────────── Ordinamento & Paginazione ─────────────────────────────────
        $q->orderBy($sortable[$sort], $dir)
          ->orderBy('product_returns.id', 'desc'); // stabilizza a parità di campo

        $perPage = (int) $request->query('per_page', 25);
        if ($perPage < 1)   $perPage = 25;
        if ($perPage > 100) $perPage = 100;

        $returns = $q->paginate($perPage)->appends($request->query());

        // ───────────────────────────────── Dataset per il modale ─────────────────────────────────
        // NB: niente logica extra, solo liste per select/autocomplete del form
        $customers = Customer::query()
            ->orderBy('company')
            ->get(['id', 'company']); // prendiamo entrambi i campi per compatibilità UI

        $products = Product::query()
            ->orderBy('sku')
            ->get(['id', 'sku', 'name']);

        $fabrics = Fabric::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $colors = Color::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        $returnWarehouseId = Warehouse::where('type', 'return')->value('id'); // id di MG-RETURN

        // ───────────────────────────────── Render view ─────────────────────────────────
        return view('pages.orders.returns.index', [
            // tabella
            'returns' => $returns,
            'filters' => $filters,
            'sort'    => $sort,
            'dir'     => $dir,

            // modale
            'customers'         => $customers,
            'products'          => $products,
            'fabrics'           => $fabrics,
            'colors'            => $colors,
            'warehouses'        => $warehouses,
            'returnWarehouseId' => $returnWarehouseId,
        ]);
    }
}
