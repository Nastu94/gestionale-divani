<?php
/**
 * Livewire Component: Elenco righe d’ordine cliente **da evadere** (uscite di magazzino).
 *
 * • Legge la VIEW `v_order_item_phase_qty` per sapere quante unità di ciascuna riga
 *   si trovano nella fase corrente ($phase).
 * • Espone filtri e ordinamento tramite query-string, ma senza ricaricare la pagina
 *   (grazie a Livewire + Alpine).
 * • Le stesse query sono replicate lato controller (`StockLevelController@indexExit`)
 *   per SEO / fallback in caso di assenza di JS.
 *
 * @author   ACME S.p.A.
 * @copyright 2025
 */

namespace App\Livewire\Warehouse;

use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ExitTable extends Component
{
    use WithPagination;

    /*──────────────────────────────────────────────────────────────*
     |  Proprietà pubbliche → Livewire le sincronizza su URL        |
     *──────────────────────────────────────────────────────────────*/
    public int     $phase   = 0;      // KPI selezionata (0=Inserito … 6=Spedizione)
    public ?string $sort    = '';     // '' = nessun ordinamento
    public string  $dir     = 'asc';  // direzione sort
    public array   $filters = [       // filtri di colonna
        'customer'      => null,
        'order_number'  => null,
        'product'       => null,
        'order_date'    => null,
        'delivery_date' => null,
        'value'         => null,
    ];
    public int     $perPage = 100;    // righe per pagina (100 / 250 / 500)

    /**
     * Mappa proprietà ⇄ query-string.
     * `except` evita di sporcare l’URL quando si usa il valore di default.
     */
    protected $queryString = [
        'phase'    => ['except' => 0],
        'sort'     => ['except' => ''],
        'dir'      => ['except' => 'asc'],
        'filters.customer'       => ['except' => ''],
        'filters.order_number'   => ['except' => ''],
        'filters.product'        => ['except' => ''],
        'filters.order_date'     => ['except' => ''],
        'filters.delivery_date'  => ['except' => ''],
        'filters.value'          => ['except' => ''],
        'filters.qty_in_phase'   => ['except' => ''],
        'page'     => ['except' => 1],
        'perPage'  => ['except' => 100],
    ];

    /**
     * Qualsiasi variazione diversa da `page` resetta la paginazione.
     */
    public function updating(string $prop): void
    {
        if ($prop !== 'page') {
            $this->resetPage();
        }
    }

    /*──────────────────────────────────────────────────────────────*
     |  Render: costruisce KPI + query dei record da mostrare       |
     *──────────────────────────────────────────────────────────────*/
    public function render()
    {
        /*――――――― KPI cards: pezzi totali per fase ―――――――*/
        $kpiCounts = DB::table('v_order_item_phase_qty')
            ->selectRaw('phase, SUM(qty_in_phase) AS qty')
            ->where('qty_in_phase', '>', 0)
            ->groupBy('phase')
            ->pluck('qty', 'phase');     // array [ phase => qty ]

        /*― White-list dei campi ordinabili ―*/
        $allowedSorts = [
            'customer', 'order_number', 'product',
            'order_date', 'delivery_date',
            'value', 'qty_in_phase',
        ];
        if ($this->sort !== '' && ! in_array($this->sort, $allowedSorts, true)) {
            $this->sort = ''; // fallback di sicurezza
        }

        /*――――――― Query principale ―――――――*/
        $rows = OrderItem::query()
            /* Join alla view delle quantità per fase */
            ->join('v_order_item_phase_qty as pq', function ($j) {
                $j->on('pq.order_item_id', '=', 'order_items.id')
                  ->where('pq.phase', $this->phase)
                  ->where('pq.qty_in_phase', '>', 0);
            })
            /* Join tabelle correlate */
            ->join('orders   as o',  'o.id',  '=', 'order_items.order_id')
            ->leftJoin('customers as c', 'c.id',  '=', 'o.customer_id')
            ->leftJoin('order_numbers as on', 'on.id', '=', 'o.order_number_id')
            ->leftJoin('products as p', 'p.id', '=', 'order_items.product_id')

            /* Selezione campi + alias calcolati */
            ->addSelect([
                'order_items.*',
                'pq.qty_in_phase',
                DB::raw('(order_items.quantity * order_items.unit_price) AS value'),

                'c.company    AS customer',
                'on.number    AS order_number',
                'p.sku        AS product',
                'p.name       AS product_name',
                'o.ordered_at AS order_date',
                'o.delivery_date',
            ])

            /*―――― Filtri dinamici ――――*/
            ->when($this->filters['customer']      ?? null,
                fn ($q, $v) => $q->where('c.company', 'like', "%{$v}%"))
            ->when($this->filters['order_number']  ?? null,
                fn ($q, $v) => $q->where('on.number', 'like', "%{$v}%"))
            ->when($this->filters['product']       ?? null,
                fn ($q, $v) => $q->where(function ($qq) use ($v) {
                    $qq->where('p.sku', 'like', "%{$v}%")
                       ->orWhere('p.name', 'like', "%{$v}%");
                }))
            ->when($this->filters['order_date']    ?? null,
                fn ($q, $v) => $q->whereDate('o.ordered_at', $v))
            ->when($this->filters['delivery_date'] ?? null,
                fn ($q, $v) => $q->whereDate('o.delivery_date', $v))
            /* NB: ricalcoliamo l’espressione, perché l’alias `value`
               NON è ancora disponibile in WHERE */
            ->when($this->filters['value']         ?? null,
                fn ($q, $v) => $q->whereRaw(
                    '(order_items.quantity * order_items.unit_price) >= ?', [$v]
                ))

            /*―――― Ordinamento (match expression PHP 8.4) ――――*/
            ->tap(function ($q) {
                match ($this->sort) {
                    'customer'      => $q->orderBy('c.company',       $this->dir),
                    'order_number'  => $q->orderBy('on.number',       $this->dir),
                    'product'       => $q->orderBy('p.sku',           $this->dir),
                    'qty_in_phase'  => $q->orderBy('pq.qty_in_phase', $this->dir),
                    'order_date'    => $q->orderBy('o.ordered_at',    $this->dir),
                    'delivery_date' => $q->orderBy('o.delivery_date', $this->dir),
                    'value'         => $q->orderBy('value',           $this->dir),
                    default         => null, // nessun ordinamento
                };
            })

            /*―――― Paginazione ――――*/
            ->paginate($this->perPage);

        /*――――― Render view Blade con dati Livewire ―――――*/
        return view('livewire.warehouse.exit-table', [
            'exitRows'  => $rows,
            'kpiCounts' => $kpiCounts,

            /* Props ritrasmesse ai componenti <x-th-menu-live> */
            'phase'     => $this->phase,
            'sort'      => $this->sort,
            'dir'       => $this->dir,
            'filters'   => $this->filters,
        ]);
    }
}
