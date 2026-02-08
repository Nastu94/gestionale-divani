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
 */

namespace App\Livewire\Warehouse;

use App\Actions\AdvanceOrderItemPhaseAction;
use App\Enums\ProductionPhase;
use App\Models\Ddt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WorkOrder;
use App\Services\Ddt\DdtService;
use App\Services\WorkOrders\WorkOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
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

    /* ───── modal Avanza ───── */
    public ?int   $advItemId    = null;   // riga selezionata
    public float  $advMaxQty    = 0;      // pezzi residui in fase
    public float  $advQuantity  = 0;      // input utente
    public ?string $advOperator  = null;  // operatore

    /*──────────── modal ROLLBACK ──────────────*/
    public ?int  $rbItemId   = null;
    public float $rbMaxQty   = 0;
    public float $rbQuantity = 0;
    public string $rbReason  = ''; // motivo del rollback
    public bool   $rbReuse   = false; // modalità 'reuse' o 'scrap' 

    /*──────────── Drawer DDT ──────────────*/
    public bool $ddtDrawerOpen = false;
    public ?int $ddtDrawerOrderId = null;
    public ?string $ddtDrawerOrderNumber = null;

    /** @var array<int,array{id:int,number:int,year:int,issued_at:string}> */
    public array $ddtDrawerDdts = [];

    /*──────────── Drawer BUONI ──────────────*/
    public bool $woDrawerOpen = false;
    public ?int $woDrawerOrderId = null;
    public ?string $woDrawerOrderNumber = null;

    /** @var array<int,array{id:int,number:int,year:int,issued_at:string}> */
    public array $woDrawerWorkOrders = [];

    /* ───────────────────────────────────────────────────────────────*
     |  Eventi Livewire: rispondono a click su pulsanti o input    |
     *───────────────────────────────────────────────────────────────*/
    protected $listeners = [
        'open-advance'   => 'openAdvance',
        'confirm-advance'=> 'confirmAdvance',
        'confirmRollback'=> 'confirmRollback',
        'print-ddt'       => 'printDdt',
    ];

    /**
     * Qualsiasi variazione diversa da `page` resetta la paginazione.
     */
    public function updating(string $prop): void
    {
        if ($prop !== 'page') {
            $this->resetPage();
        }

        // chiudi toolbar se l’utente cambia KPI fase
        if ($prop === 'phase') {
            $this->dispatch('close-row');   // evento JS → Alpine imposta openId = null
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
            ->pluck('qty', 'phase');    // array [ phase => qty ]

        /*― White-list dei campi ordinabili ―*/
        $allowedSorts = [
            'customer', 'order_number', 'product',
            'order_date', 'delivery_date',
            'value', 'qty_in_phase',
        ];
        if ($this->sort !== '' && ! in_array($this->sort, $allowedSorts, true)) {
            $this->sort = ''; // fallback di sicurezza
        }

        /* ---------- sub-query con qty aggregate ---------- */
        $phase   = $this->phase;
        $pqSub   = DB::table('v_order_item_phase_qty')
            ->select(
                'order_item_id',
                DB::raw('SUM(qty_in_phase) AS qty_in_phase')
            )
            ->where('phase', $phase)
            ->groupBy('order_item_id');

        /*――――――― Query principale ―――――――*/
        $rows = OrderItem::query()
            ->joinSub($pqSub, 'pq', 'pq.order_item_id', '=', 'order_items.id')
            ->join('orders   as o',  'o.id',  '=', 'order_items.order_id')
            ->leftJoin('customers as c',      'c.id',  '=', 'o.customer_id')
            ->leftJoin('occasional_customers as oc', 'oc.id', '=', 'o.occasional_customer_id')
            ->leftJoin('order_numbers as on', 'on.id', '=', 'o.order_number_id')
            ->leftJoin('products as p',       'p.id',  '=', 'order_items.product_id')

            ->addSelect([
                'order_items.*',
                'pq.qty_in_phase',
                DB::raw('(order_items.quantity * order_items.unit_price) AS value'),
                DB::raw('COALESCE(c.company, oc.company) AS customer'),
                'on.number        as order_number',
                'p.sku            as product',
                'p.name           as product_name',
                'o.ordered_at     as order_date',
                'o.delivery_date',
            ])

            /*―――― Filtri dinamici ――――*/
            ->when($this->filters['customer'] ?? null, function ($q, $v) {
                $q->where(function ($qq) use ($v) {
                    $qq->where('c.company',  'like', "%{$v}%")
                        ->orWhere('oc.company','like', "%{$v}%");
                });
            })
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
                    'customer'      => $q->orderByRaw('COALESCE(c.company, oc.company) '.$this->dir),
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

    /*──────────────────────────────────────────────────────────────*
     |  Azioni per la gestione delle fasi di avanzamento           |
     *──────────────────────────────────────────────────────────────*/
    public function openAdvance(int $itemId, float $max): void
    {
        /* ①  memorizza nello stato */
        $this->advItemId   = $itemId;
        $this->advMaxQty   = $max;
        $this->advQuantity = $max;     // default = 100 %

        /* ②  mostra il modal */
        $this->dispatch('show-adv-modal',             // evento browser
            id: $itemId,
            maxQty: $max,
            defaultQty: $max
        );
    }

    public function confirmAdvance(float $qty = null): void
    {
        /* ── DEBUG ───────────────────────────────────────────── */
        Log::debug('[confirmAdvance] in', [
            'advItemId'   => $this->advItemId,
            'advMaxQty'   => $this->advMaxQty,
            'advQuantity' => $this->advQuantity,
            'param_qty'   => $qty,
        ]);
        /* ────────────────────────────────────────────────────── */

        if ($qty !== null) {
            $this->advQuantity = $qty;
        }

        $this->validate([
            'advQuantity' => ['required','numeric','gt:0','lte:'.$this->advMaxQty],
            'advOperator' => ['nullable','string','max:255'],
        ], [], ['advQuantity'=>'quantità', 'advOperator'=>'operatore']);

        try {
            Log::debug('[confirmAdvance] validated – dispatch action');

            app(AdvanceOrderItemPhaseAction::class, [
                'item'       => OrderItem::findOrFail($this->advItemId),
                'quantity'   => $this->advQuantity,
                'user'       => auth()->user(),
                'fromPhase'  => ProductionPhase::from($this->phase),   // 👈 KPI selezionata
                'isRollback' => false,
                'operator'   => $this->advOperator ? trim($this->advOperator) : null,
            ])->execute();

            Log::info('[confirmAdvance] OK', ['item' => $this->advItemId]);

            session()->flash('success', 'Fase avanzata correttamente.');
            $this->dispatch('close-row');  
            $this->reset('advItemId','advMaxQty','advQuantity', 'advOperator');
            $this->resetPage();               // refresh KPI + righe
        } catch (\Throwable $e) {
            Log::error('[confirmAdvance] ERROR', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Avvia il rollback di una fase, mostrando il modal.
     * @param int $itemId ID dell'ordine da fare rollback.
     * @param float $max Massimo pezzi da fare rollback.
     */
    public function openRollback(int $itemId, float $max): void
    {
        $this->rbItemId   = $itemId;
        $this->rbMaxQty   = $max;
        $this->rbQuantity = $max;
        $this->rbReason   = ''; // reset motivo
        $this->rbReuse    = false; // reset modalità

        $this->dispatch('show-rollback-modal',    // evento browser
            id:        $itemId,
            maxQty:    $max,
            defaultQty:$max
        );
    }

    /**
     * Conferma il rollback di una fase, con modalità 'reuse' o 'scrap'.
     * @param array $data Dati inviati dal form di rollback.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function confirmRollback(array $payload): void
    {
        $this->rbItemId   = (int)   ($payload['id']  ?? 0);
        $this->rbQuantity = (float) ($payload['qty'] ?? 0);
        $this->rbMaxQty   = (float) ($payload['max'] ?? $this->rbQuantity);
        $this->rbReason   = $payload['reason'] ?? null;
        $this->rbReuse    = ($payload['reuse'] ?? false);

        $this->validate([
            'rbQuantity' => ['required','numeric','gt:0','lte:'.$this->rbMaxQty],
        ], [], ['rbQuantity'=>'quantità']);

        try {
            $result = app(AdvanceOrderItemPhaseAction::class, [
                'item'         => OrderItem::findOrFail($this->rbItemId),
                'quantity'     => $this->rbQuantity,
                'user'         => auth()->user(),
                'fromPhase'    => ProductionPhase::from($this->phase),
                'isRollback'   => true,
                'reason'       => $this->rbReason,
                'rollbackMode' => $this->rbReuse ? 'reuse' : 'scrap',
            ])->execute();

            /* ───── messaggio dinamico ───── */
            $msg = 'Rollback registrato.';
            if (!empty($result['po_numbers']) && $result['po_numbers']->isNotEmpty()) {
                $list = $result['po_numbers']->implode(',');
                $msg .= " Creati ordini fornitore {$list}.";
            }

            session()->flash('success', $msg);
            $this->dispatch('close-row');
            $this->reset('rbItemId','rbMaxQty','rbQuantity', 'rbReason', 'rbReuse');
            $this->resetPage();

        } catch (\Throwable $e) {
            Log::error('[confirmRollback] ERROR', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('error',$e->getMessage());
        }
    }

    /**
     * Genera il DDT e apre la finestra di stampa.
     *
     * @param int $orderItemId ID della riga ordine cliccata (serve per risalire all’ordine).
     */
    public function printDdt(int $orderItemId): void
    {
        try {
            /* Permesso: stessa logica “uscite” */
            if (! auth()->user()->can('stock.exit')) {
                throw ValidationException::withMessages([
                    'auth' => 'Non hai il permesso per generare il DDT.',
                ]);
            }

            /* 1) Genera DDT (DB) */
            $ddt = app(DdtService::class)->createForOrderItem($orderItemId, auth()->user());

            /* 2) Prepara URL firmato verso pagina stampa */
            $printUrl = URL::temporarySignedRoute(
                'warehouse.ddt.print',
                now()->addMinutes(5),
                ['ddt' => $ddt->id]
            );

            /* 3) Apri nuova finestra (browser) */
            $this->dispatch('open-print-window', url: $printUrl);

            session()->flash('success', "DDT nr. {$ddt->number} generato. Apertura stampa…");
        } catch (\Throwable $e) {
            Log::error('[printDdt] ERROR', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', $e->getMessage());
        }
    }

    /*──────────────────────────── Drawer DDT: elenco e ristampa ───────────────────────────*/

    public function openDdtDrawer(int $orderId): void
    {
        try {
            if ($this->phase !== 6) {
                return; // visibile solo in fase 6, ma sicurezza lato server
            }

            if (! auth()->user()->can('stock.exit')) {
                throw ValidationException::withMessages(['auth' => 'Non hai il permesso per visualizzare i DDT.']);
            }

            $order = Order::query()
                ->with('orderNumber')
                ->findOrFail($orderId);

            $this->ddtDrawerOrderId = $order->id;
            $this->ddtDrawerOrderNumber = (string)($order->orderNumber?->number ?? $order->id);

            $ddts = Ddt::query()
                ->where('order_id', $order->id)
                ->orderByDesc('issued_at')
                ->orderByDesc('id')
                ->get(['id', 'year', 'number', 'issued_at']);

            $this->ddtDrawerDdts = $ddts->map(fn (Ddt $d) => [
                'id'        => $d->id,
                'year'      => (int) $d->year,
                'number'    => (int) $d->number,
                'issued_at' => $d->issued_at ? $d->issued_at->format('d/m/Y') : '',
            ])->all();

            $this->ddtDrawerOpen = true;
        } catch (\Throwable $e) {
            Log::error('[openDdtDrawer] ERROR', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeDdtDrawer(): void
    {
        $this->ddtDrawerOpen = false;
        $this->reset('ddtDrawerOrderId', 'ddtDrawerOrderNumber', 'ddtDrawerDdts');
    }

    public function updatedDdtDrawerOpen($value): void
    {
        // se chiuso da Alpine via entangle
        if (! $value) {
            $this->reset('ddtDrawerOrderId', 'ddtDrawerOrderNumber', 'ddtDrawerDdts');
        }
    }

    public function printExistingDdt(int $ddtId): void
    {
        try {
            if (! auth()->user()->can('stock.exit')) {
                throw ValidationException::withMessages(['auth' => 'Non hai il permesso per stampare i DDT.']);
            }

            $ddt = Ddt::query()->findOrFail($ddtId);

            // sicurezza: ristampa solo DDT dell'ordine aperto nel drawer
            if ($this->ddtDrawerOrderId !== null && (int)$ddt->order_id !== (int)$this->ddtDrawerOrderId) {
                throw ValidationException::withMessages(['ddt' => 'DDT non coerente con l’ordine selezionato.']);
            }

            $printUrl = URL::temporarySignedRoute(
                'warehouse.ddt.print',
                now()->addMinutes(5),
                ['ddt' => $ddt->id]
            );

            $this->dispatch('open-print-window', url: $printUrl);
        } catch (\Throwable $e) {
            Log::error('[printExistingDdt] ERROR', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', $e->getMessage());
        }
    }

    /*──────────────────────────── Drawer BUONI: elenco e ristampa ───────────────────────────*/
    public function printWorkOrder(int $orderId): void
    {
        try {
            if ($this->phase >= 6) {
                return; // sicurezza: in spedizione c'è il DDT
            }

            if (! auth()->user()->can('stock.exit')) {
                throw ValidationException::withMessages(['auth' => 'Non hai il permesso per generare il buono.']);
            }

            // 1) Crea buono con SOLO delta
            $wo = app(WorkOrderService::class)->createForOrderAndPhase(
                orderId: $orderId,
                phase: $this->phase,
                user: auth()->user()
            );

            // 2) URL firmato verso pagina stampa
            $printUrl = URL::temporarySignedRoute(
                'warehouse.work_orders.print',
                now()->addMinutes(5),
                ['workOrder' => $wo->id]
            );

            // 3) Apri finestra stampa
            $this->dispatch('open-print-window', url: $printUrl);

            session()->flash('success', "Buono {$wo->number}/{$wo->year} generato. Apertura stampa…");
        } catch (\Throwable $e) {
            Log::error('[printWorkOrder] ERROR', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', $e instanceof ValidationException ? $e->getMessage() : $e->getMessage());
        }
    }

    public function openWorkOrderDrawer(int $orderId): void
    {
        try {
            if ($this->phase >= 6) return;

            if (! auth()->user()->can('stock.exit')) {
                throw ValidationException::withMessages(['auth' => 'Non hai il permesso per visualizzare i buoni.']);
            }

            $order = Order::query()->with('orderNumber')->findOrFail($orderId);

            $this->woDrawerOrderId = $order->id;
            $this->woDrawerOrderNumber = (string)($order->orderNumber?->number ?? $order->id);

            $wos = WorkOrder::query()
                ->where('order_id', $order->id)
                ->where('phase', $this->phase)
                ->orderByDesc('issued_at')
                ->orderByDesc('id')
                ->get(['id','year','number','issued_at']);

            $this->woDrawerWorkOrders = $wos->map(fn (WorkOrder $w) => [
                'id'        => $w->id,
                'year'      => (int) $w->year,
                'number'    => (int) $w->number,
                'issued_at' => $w->issued_at ? $w->issued_at->format('d/m/Y H:i') : '',
            ])->all();

            $this->woDrawerOpen = true;
        } catch (\Throwable $e) {
            Log::error('[openWorkOrderDrawer] ERROR', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', $e->getMessage());
        }
    }

    public function closeWorkOrderDrawer(): void
    {
        $this->woDrawerOpen = false;
        $this->reset('woDrawerOrderId','woDrawerOrderNumber','woDrawerWorkOrders');
    }

    public function updatedWoDrawerOpen($value): void
    {
        if (! $value) {
            $this->reset('woDrawerOrderId','woDrawerOrderNumber','woDrawerWorkOrders');
        }
    }

    public function printExistingWorkOrder(int $workOrderId): void
    {
        try {
            if (! auth()->user()->can('stock.exit')) {
                throw ValidationException::withMessages(['auth' => 'Non hai il permesso per stampare i buoni.']);
            }

            $wo = WorkOrder::query()->findOrFail($workOrderId);

            // sicurezza: deve appartenere all'ordine aperto e alla fase corrente
            if ($this->woDrawerOrderId !== null && (int)$wo->order_id !== (int)$this->woDrawerOrderId) {
                throw ValidationException::withMessages(['wo' => 'Buono non coerente con l’ordine selezionato.']);
            }
            if ((int)$wo->phase !== (int)$this->phase) {
                throw ValidationException::withMessages(['wo' => 'Buono non coerente con la fase corrente.']);
            }

            $printUrl = URL::temporarySignedRoute(
                'warehouse.work_orders.print',
                now()->addMinutes(5),
                ['workOrder' => $wo->id]
            );

            $this->dispatch('open-print-window', url: $printUrl);
        } catch (\Throwable $e) {
            Log::error('[printExistingWorkOrder] ERROR', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            session()->flash('error', $e->getMessage());
        }
    }
}
