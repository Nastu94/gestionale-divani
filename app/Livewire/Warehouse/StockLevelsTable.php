<?php
/**
 * Livewire Component: Elenco giacenze di magazzino (Warehouse ▸ Stock levels).
 *
 * - Dataset tratto da `stock_levels` (snapshot quantità) unito a `components`
 *   e al totale impegnato in `stock_reservations`.
 * - Filtri / ordinamento / paginazione reattivi via query-string
 *   (stesso pattern di Warehouse\ExitTable).
 * - Espansione **di UNA sola riga alla volta** per mostrare i lotti (>0 qty).
 *
 * Path  : app/Livewire/Warehouse/StockLevelsTable.php
 * Author: Gestionale Divani – 2025
 */

namespace App\Livewire\Warehouse;

use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class StockLevelsTable extends Component
{
    use WithPagination;

    /*──────────────────────────────────────────────────────────────*
     |  Proprietà pubbliche ↔ Query-string                          |
     *──────────────────────────────────────────────────────────────*/
    /** components | products */
    public string  $mode = 'components';
    public ?string $sort    = '';      // '' = nessun ordinamento
    public string  $dir     = 'asc';   // asc | desc
    public array   $filters = [        // filtri di colonna
        'component_code'        => '',
        'component_description' => '',
        'uom'                   => '',
        'reserved_quantity'     => '',   // range min (≥)
        'fabric'                => '',
        'color'                 => '',
    ];
    public int     $perPage  = 50;     // 50 | 100 | 250

    /** Id della riga attualmente espansa (solo una alla volta). */
    public ?int $expandedId = null;

    /** Cache lotti per id stock_level: [ id => Collection ] */
    public array $lots = [];

    /**↔ query-string map  */
    protected array $queryString = [
        'mode'                                  => ['except' => 'components'],
        'sort'                                  => ['except' => ''],
        'dir'                                   => ['except' => 'asc'],
        'filters.component_code'                => ['except' => ''],
        'filters.component_description'         => ['except' => ''],
        'filters.uom'                           => ['except' => ''],
        'filters.reserved_quantity'             => ['except' => ''],
        'filters.fabric'                        => ['except' => ''],
        'filters.color'                         => ['except' => ''],
        'page'                                  => ['except' => 1],
        'perPage'                               => ['except' => 50],
    ];

    /*──────────────────────────────────────────────────────────────*
     |  ACL & Reset paginazione                                     |
     *──────────────────────────────────────────────────────────────*/

    /** Permesso “stock.view” richiesto a monte. */
    public function mount(): void
    {
        abort_unless(auth()->user()->can('stock.view'), 403);
    }

    /** Qualsiasi variazione ≠ page ⇒ resetta paginazione. */
    public function updating(string $prop): void
    {
        if ($prop !== 'page') {
            $this->resetPage();
        }
        if ($prop === 'mode') {
            $this->expandedId = null;
            $this->lots = [];
            $this->sort = '';

            if ($this->mode === 'components') {
                // passando a COMPONENTI: i filtri prodotti non servono
                $this->filters['fabric'] = '';
                $this->filters['color']  = '';
            } else {
                // passando a PRODOTTI: l'UM non c'è
                $this->filters['uom'] = '';
            }
        }
    }

    /*──────────────────────────────────────────────────────────────*
     |  Azione: espandi / collassa riga                             |
     *──────────────────────────────────────────────────────────────*/
    public function toggle(int $id): void
    {
        if ($this->mode !== 'components') return;

        $this->expandedId = $this->expandedId === $id ? null : $id;

        if ($this->expandedId && ! isset($this->lots[$id])) {
            $this->lots[$id] = DB::table('stock_level_lots')
                ->where('stock_level_id', $id)
                ->where('quantity', '>', 0)
                ->select('internal_lot_code', 'supplier_lot_code', 'quantity')
                ->orderBy('internal_lot_code')
                ->get();
        }
    }

    /*──────────────────────────────────────────────────────────────*
     |  Query builder con filtri / sort                             |
     *──────────────────────────────────────────────────────────────*/
    private function query(): \Illuminate\Database\Query\Builder|Builder
    {
        return $this->mode === 'components'
            ? $this->queryComponents()
            : $this->queryProducts();
    }

    /** Vista COMPONENTI (identica alla tua, invariata) */
    private function queryComponents(): Builder
    {
        $allowedSorts = ['component_code','component_description','uom','quantity','reserved_quantity'];
        if ($this->sort !== '' && ! in_array($this->sort, $allowedSorts, true)) {
            $this->sort = '';
        }

        $resSub = DB::table('stock_reservations')
            ->select('stock_level_id', DB::raw('SUM(quantity) AS reserved_quantity'))
            ->groupBy('stock_level_id');

        $q = StockLevel::query()
            ->join('components as c', 'c.id', '=', 'stock_levels.component_id')
            ->leftJoinSub($resSub, 'r', 'r.stock_level_id', '=', 'stock_levels.id')
            ->where('stock_levels.quantity', '>', 0)
            ->select([
                'stock_levels.*',
                'c.code             as component_code',
                'c.description      as component_description',
                'c.unit_of_measure  as uom',
                DB::raw('COALESCE(r.reserved_quantity,0) AS reserved_quantity'),
            ])

            // Filtri
            ->when($this->filters['component_code'],
                fn ($q,$v) => $q->where('c.code','like',"%{$v}%"))
            ->when($this->filters['component_description'],
                fn ($q,$v) => $q->where('c.description','like',"%{$v}%"))
            ->when($this->filters['uom'],
                fn ($q,$v) => $q->where('c.unit_of_measure',$v))
            ->when($this->filters['reserved_quantity'],
                fn ($q,$v) => $q->havingRaw('reserved_quantity >= ?', [$v]));

        // Sort
        $q->tap(function ($q) {
            match ($this->sort) {
                'component_code'        => $q->orderBy('c.code',            $this->dir),
                'component_description' => $q->orderBy('c.description',     $this->dir),
                'uom'                   => $q->orderBy('c.unit_of_measure', $this->dir),
                'reserved_quantity'     => $q->orderBy('reserved_quantity', $this->dir),
                'quantity'              => $q->orderBy('stock_levels.quantity', $this->dir),
                default                 => null,
            };
        });

        return $q;
    }

    /** Vista PRODOTTI RESI (product_stock_levels) */
    private function queryProducts(): \Illuminate\Database\Query\Builder
    {
        $codeFilter = $this->filters['component_code'] ?? '';
        $descFilter = $this->filters['component_description'] ?? '';
        $resMin     = $this->filters['reserved_quantity'] ?? '';
        // NEW
        $fabFilter  = $this->filters['fabric'] ?? '';
        $colFilter  = $this->filters['color']  ?? '';

        $allowedSorts = ['component_code','component_description','quantity','reserved_quantity','fabric','color'];
        if ($this->sort !== '' && ! in_array($this->sort, $allowedSorts, true)) {
            $this->sort = '';
        }

        $q = DB::table('product_stock_levels as psl')
            ->join('products as p', 'p.id', '=', 'psl.product_id')
            ->leftJoin('fabrics as f', 'f.id', '=', 'psl.fabric_id')
            ->leftJoin('colors  as co','co.id','=', 'psl.color_id')
            ->where('psl.quantity', '>', 0)
            ->selectRaw('
                MIN(psl.id) as id,
                p.sku as product_code,
                p.description as product_description,
                f.name as fabric_name,
                co.name as color_name,
                COALESCE(SUM(psl.quantity),0) as quantity,
                COALESCE(SUM(CASE WHEN psl.reserved_for IS NOT NULL THEN psl.quantity ELSE 0 END),0) as reserved_quantity
            ')
            ->groupBy('psl.product_id','psl.fabric_id','psl.color_id','p.sku','p.description','f.name','co.name')

            // Filtri esistenti
            ->when($codeFilter, fn($q,$v) => $q->where('p.sku', 'like', "%{$v}%"))
            ->when($descFilter, fn($q,$v) => $q->where('p.description', 'like', "%{$v}%"))
            ->when($resMin,     fn($q,$v) => $q->havingRaw('reserved_quantity >= ?', [$v]))

            // NEW: filtri su tessuto/colore
            ->when($fabFilter,  fn($q,$v) => $q->where('f.name', 'like', "%{$v}%"))
            ->when($colFilter,  fn($q,$v) => $q->where('co.name','like', "%{$v}%"));

        // Ordinamenti (già presenti + fabric/color)
        $q->tap(function ($q) {
            match ($this->sort) {
                'component_code'        => $q->orderBy('product_code',       $this->dir),
                'component_description' => $q->orderBy('product_description', $this->dir),
                'reserved_quantity'     => $q->orderBy('reserved_quantity',  $this->dir),
                'quantity'              => $q->orderBy('quantity',           $this->dir),
                'fabric'                => $q->orderBy('fabric_name',        $this->dir),
                'color'                 => $q->orderBy('color_name',         $this->dir),
                default                 => null,
            };
        });

        return $q;
    }

    /*──────────────────────────────────────────────────────────────*
     |  Getter paginato (propagate query-string)                    |
     *──────────────────────────────────────────────────────────────*/
    public function getStockLevelsProperty()
    {
        return $this->query()
            ->paginate($this->perPage)
            ->withQueryString();
    }

    /*──────────────────────────────────────────────────────────────*
     |  Render                                                      |
     *──────────────────────────────────────────────────────────────*/
    public function render()
    {
        return view('livewire.warehouse.stock-levels-table', [
            'levels'  => $this->stockLevels,
            'mode'    => $this->mode,
            'sort'    => $this->sort,
            'dir'     => $this->dir,
            'filters' => $this->filters,
        ]);
    }
}
