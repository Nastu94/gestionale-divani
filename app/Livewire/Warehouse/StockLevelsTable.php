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
    public ?string $sort    = '';      // '' = nessun ordinamento
    public string  $dir     = 'asc';   // asc | desc
    public array   $filters = [        // filtri di colonna
        'component_code'        => '',
        'component_description' => '',
        'uom'                   => '',
        'reserved_quantity'     => '',   // range min (≥)
    ];
    public int     $perPage  = 50;     // 50 | 100 | 250

    /** Id della riga attualmente espansa (solo una alla volta). */
    public ?int $expandedId = null;

    /** Cache lotti per id stock_level: [ id => Collection ] */
    public array $lots = [];

    /**↔ query-string map  */
    protected array $queryString = [
        'sort'                                  => ['except' => ''],
        'dir'                                   => ['except' => 'asc'],
        'filters.component_code'                => ['except' => ''],
        'filters.component_description'         => ['except' => ''],
        'filters.uom'                           => ['except' => ''],
        'filters.reserved_quantity'             => ['except' => ''],
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
    }

    /*──────────────────────────────────────────────────────────────*
     |  Azione: espandi / collassa riga                             |
     *──────────────────────────────────────────────────────────────*/
    public function toggle(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;

        /* carica lotti (>0 qty) on-demand */
        if ($this->expandedId && ! isset($this->lots[$id])) {
            $this->lots[$id] = DB::table('stock_level_lots')
                ->where('stock_level_id', $id)
                ->where('quantity', '>', 0)                    // ignora lotti scarichi
                ->select('internal_lot_code', 'supplier_lot_code', 'quantity')
                ->orderBy('internal_lot_code')
                ->get();
        }
    }

    /*──────────────────────────────────────────────────────────────*
     |  Query builder con filtri / sort                             |
     *──────────────────────────────────────────────────────────────*/
    private function query(): Builder
    {
        /* White-list colonne ordinabili */
        $allowedSorts = [
            'component_code', 'component_description', 'uom',
            'quantity', 'reserved_quantity',
        ];
        if ($this->sort !== '' && ! in_array($this->sort, $allowedSorts, true)) {
            $this->sort = '';
        }

        /* Sub-query totali impegnati (stock_reservations) */
        $resSub = DB::table('stock_reservations')
            ->select('stock_level_id', DB::raw('SUM(quantity) AS reserved_quantity'))
            ->groupBy('stock_level_id');

        return StockLevel::query()
            ->join('components as c', 'c.id', '=', 'stock_levels.component_id')
            ->leftJoinSub($resSub, 'r',   'r.stock_level_id', '=', 'stock_levels.id')
            ->where('stock_levels.quantity', '>', 0)          // esclude snapshot a zero
            ->select([
                'stock_levels.*',
                'c.code             as component_code',
                'c.description      as component_description',
                'c.unit_of_measure  as uom',
                DB::raw('COALESCE(r.reserved_quantity,0) AS reserved_quantity'),
            ])

            /*―――― Filtri dinamici ――――*/
            ->when($this->filters['component_code'],
                   fn ($q,$v) => $q->where('c.code','like',"%{$v}%"))
            ->when($this->filters['component_description'],
                   fn ($q,$v) => $q->where('c.description','like',"%{$v}%"))
            ->when($this->filters['uom'],
                   fn ($q,$v) => $q->where('c.unit_of_measure',$v))
            ->when($this->filters['reserved_quantity'],
                   fn ($q,$v) => $q->havingRaw('reserved_quantity >= ?', [$v]))

            /*―――― Ordinamento ――――*/
            ->tap(function ($q) {
                match ($this->sort) {
                    'component_code'        => $q->orderBy('c.code',          $this->dir),
                    'component_description' => $q->orderBy('c.description',   $this->dir),
                    'uom'                   => $q->orderBy('c.unit_of_measure',$this->dir),
                    'reserved_quantity'     => $q->orderBy('reserved_quantity',$this->dir),
                    'quantity'              => $q->orderBy('stock_levels.quantity', $this->dir),
                    default                 => null,
                };
            });
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
            'levels'  => $this->stockLevels, // shorthand in Blade
            'sort'    => $this->sort,
            'dir'     => $this->dir,
            'filters' => $this->filters,
        ]);
    }
}
