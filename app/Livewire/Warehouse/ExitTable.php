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
use App\Exceptions\ForceReservationRequiredException;
use App\Models\Ddt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WorkOrder;
use App\Services\ForceReservationPlanner;
use App\Services\ForceReservationExecutor;
use App\Services\Ddt\DdtService;
use App\Services\WorkOrders\WorkOrderService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
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
        'qty_in_phase'  => null,
        'shipping_zone'  => null,
    ];
    public int     $perPage = 100;    // righe per pagina (50 / 100 / 250 / 500)

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
        'filters.shipping_zone'  => ['except' => ''],
        //'page'     => ['except' => 1],
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

    /*──────────── Modal conferma DDT ──────────────*/
    public ?int $ddtConfirmOrderItemId = null;      // Riga ordine selezionata per il DDT
    public ?int $ddtConfirmOrderId = null;          // Ordine padre della riga
    public ?string $ddtConfirmOrderNumber = null;   // Numero ordine mostrato nel modale
    public ?string $ddtConfirmCustomer = null;      // Cliente mostrato nel modale
    public ?string $ddtConfirmNote = null;          // Note da salvare su orders.note
    public ?string $ddtConfirmUnitPriceOverride = null; // Sovrascrittura prezzo unitario (opzionale)
    public bool $ddtConfirmOpen = false;            // Apertura/chiusura modale

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
     * ID delle righe ordine selezionate nella tabella uscite.
     *
     * La selezione è pensata per la pagina corrente:
     * quando cambiano fase, filtri, ordinamento, paginazione o perPage,
     * viene svuotata per evitare stampe incoerenti.
     *
     * @var array<int, int>
     */
    public array $selectedExitRowIds = [];

    /**
     * Mancanti strutturati (payload) per mostrare il pulsante "Forza Prenotazione"
     * e, nel prossimo step, costruire il piano di riallocazione.
     *
     * @var array<int, array<string, int|float|string>>
     */
    public array $forceMissingComponents = [];

    /**
     * Flag UI: se true mostri il pulsante "Forza Prenotazione".
     */
    public bool $canForceReservation = false;

    /**
     * Piano di riallocazione calcolato (solo preview).
     *
     * @var array<string, mixed>|null
     */
    public ?array $forcePlan = null;

    /**
     * True se la UI deve mostrare la modale di conferma riallocazione.
     */
    public bool $showForceReservationModal = false;

    /**
     * Qualsiasi variazione diversa dalla paginazione e dalla selezione
     * resetta la pagina corrente.
     *
     * Nota Livewire 3:
     * - la paginazione aggiorna proprietà interne tipo "paginators.page"
     * - la selezione checkbox aggiorna selectedExitRowIds
     */
    public function updating(string $prop): void
    {
        /*
        * Livewire aggiorna la paginazione usando proprietà interne
        * come "paginators" o "paginators.page".
        */
        $isPaginationUpdate =
            $prop === 'paginators' || str_starts_with($prop, 'paginators.');

        /*
        * Le checkbox aggiornano selectedExitRowIds.
        * Non dobbiamo resettare pagina o selezione quando l'utente
        * sta semplicemente selezionando/deselezionando righe.
        */
        $isSelectionUpdate =
            $prop === 'selectedExitRowIds' || str_starts_with($prop, 'selectedExitRowIds.');

        /*
        * Se l'utente cambia pagina, svuotiamo la selezione.
        * Così "seleziona tutto" resta riferito alle righe visibili.
        */
        if ($isPaginationUpdate) {
            $this->clearSelectedExitRows();

            return;
        }

        /*
        * Se l'utente cambia filtri, fase, ordinamento o perPage,
        * resettiamo pagina e selezione.
        */
        if (! $isSelectionUpdate) {
            $this->resetPage();
            $this->clearSelectedExitRows();
        }

        /*
        * Quando cambia KPI/fase, chiudiamo anche la toolbar della riga.
        */
        if ($prop === 'phase') {
            $this->dispatch('close-row');
        }
    }

    /**
     * Normalizza la selezione ogni volta che Livewire aggiorna
     * l'array selectedExitRowIds.
     *
     * @param  mixed  $value
     * @return void
     */
    public function updatedSelectedExitRowIds(mixed $value = null): void
    {
        $this->normalizeSelectedExitRows();
    }

    /**
     * Seleziona o deseleziona tutte le righe visibili nella pagina corrente.
     *
     * @param  array<int, int|string>  $visibleIds
     * @return void
     */
    public function toggleVisibleRows(array $visibleIds): void
    {
        /*
        * Normalizza gli ID visibili ricevuti dalla view.
        */
        $visibleIds = collect($visibleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($visibleIds->isEmpty()) {
            return;
        }

        /*
        * Normalizza la selezione corrente.
        */
        $selectedIds = collect($this->selectedExitRowIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        /*
        * Se tutte le righe visibili sono già selezionate,
        * allora la checkbox master le deseleziona.
        */
        $allVisibleSelected = $visibleIds->every(
            fn (int $id) => $selectedIds->contains($id)
        );

        if ($allVisibleSelected) {
            $this->selectedExitRowIds = $selectedIds
                ->reject(fn (int $id) => $visibleIds->contains($id))
                ->values()
                ->all();

            return;
        }

        /*
        * Altrimenti aggiungiamo tutte le righe visibili alla selezione.
        */
        $this->selectedExitRowIds = $selectedIds
            ->merge($visibleIds)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Genera una richiesta di stampa per le righe selezionate.
     *
     * Gli ID non vengono passati direttamente nella query string:
     * vengono salvati temporaneamente in cache e recuperati tramite token.
     *
     * @return void
     */
    public function printSelectedRows(): void
    {
        /*
        * Controllo permesso coerente con le azioni di uscita magazzino.
        */
        if (! auth()->user()?->can('stock.exit')) {
            session()->flash('error', 'Non hai il permesso per stampare le uscite di magazzino.');
            return;
        }

        $this->normalizeSelectedExitRows();

        if (empty($this->selectedExitRowIds)) {
            session()->flash('error', 'Seleziona almeno una riga da stampare.');
            return;
        }

        /*
        * Limite prudenziale per evitare stampe troppo grandi.
        * Puoi alzarlo se serve, ma partirei così.
        */
        if (count($this->selectedExitRowIds) > 500) {
            session()->flash('error', 'Puoi stampare al massimo 500 righe per volta.');
            return;
        }

        /*
        * Token temporaneo per recuperare gli ID lato controller.
        */
        $token = (string) Str::uuid();

        Cache::put(
            $this->printCacheKey($token),
            [
                'user_id' => auth()->id(),
                'ids'     => $this->selectedExitRowIds,
                'phase'   => $this->phase,
            ],
            now()->addMinutes(5)
        );

        /*
        * URL firmata temporanea.
        * La rotta ha middleware signed, quindi non è modificabile
        * senza invalidare la firma.
        */
        $printUrl = URL::temporarySignedRoute(
            'warehouse.exits.print.selected',
            now()->addMinutes(5),
            ['token' => $token]
        );

        /*
        * Riutilizziamo il listener JS già presente nella view.
        */
        $this->dispatch('open-print-window', url: $printUrl);
    }

    /**
     * Svuota la selezione delle righe.
     *
     * @return void
     */
    public function clearSelectedExitRows(): void
    {
        $this->selectedExitRowIds = [];
    }

    /**
     * Normalizza gli ID selezionati.
     *
     * @return void
     */
    private function normalizeSelectedExitRows(): void
    {
        $this->selectedExitRowIds = collect($this->selectedExitRowIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Restituisce la chiave cache per la stampa selezionata.
     *
     * @param  string  $token
     * @return string
     */
    private function printCacheKey(string $token): string
    {
        return "warehouse_exit_print:{$token}";
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
            'order_date', 'delivery_date', 'shipping_zone',
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
                'o.shipping_zone  as shipping_zone',                
            ])

            /*―――― Filtri dinamici ――――*/
            ->when($this->filters['customer'] ?? null, function ($q, $v) {
                $q->where(function ($qq) use ($v) {
                    $qq->where('c.company',  'like', "%{$v}%")
                        ->orWhere('oc.company','like', "%{$v}%");
                });
            })
            ->when($this->filters['shipping_zone'] ?? null, function ($q, $v) {
                $q->where('o.shipping_zone', 'like', "%{$v}%");
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
                    'shipping_zone'  => $q->orderBy('o.shipping_zone',  $this->dir),
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
        } catch (ForceReservationRequiredException $e) {

            /* -------------------------------------------------------------
            | Caso atteso: mancano prenotazioni.
            | Salviamo il payload per UI e abilitiamo il pulsante.
            *------------------------------------------------------------- */
            $this->forceMissingComponents = $e->missingComponents();
            $this->canForceReservation    = true;

            Log::warning('[confirmAdvance] ForceReservationRequired', [
                'item_id'   => $this->advItemId,
                'missing'   => $this->forceMissingComponents,
                'message'   => $e->getMessage(),
            ]);

            session()->flash('error', $e->getMessage());

        } catch (\Throwable $e) {
            Log::error('[confirmAdvance] ERROR', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Step 3: Precalcola il piano (A+B) e apre la modale riepilogativa.
     */
    public function openForceReservation(): void
    {
        /* -------------------------------------------------------------
        * Guardia: il pulsante deve essere attivo e dobbiamo avere i mancanti.
        *------------------------------------------------------------- */
        if (!$this->canForceReservation || empty($this->forceMissingComponents)) {
            session()->flash('error', 'Nessuna riallocazione disponibile: ripeti l’avanzamento per ricalcolare i mancanti.');
            return;
        }

        try {
            /** @var OrderItem $item */
            $item = OrderItem::with('order')->findOrFail($this->advItemId);

            Log::info('[openForceReservation] start', [
                'order_id'     => $item->order_id,
                'order_item_id'=> $item->id,
                'qty'          => $this->advQuantity,
                'missing_cnt'  => count($this->forceMissingComponents),
            ]);

            /** @var ForceReservationPlanner $planner */
            $planner = app(ForceReservationPlanner::class);

            $result = $planner->plan(
                urgentItem: $item,
                moveQty: (float) $this->advQuantity,
                missingComponents: $this->forceMissingComponents
            );

            if (($result['ok'] ?? false) !== true) {
                // Caso 4A: stop se non copriamo al 100%
                $this->forcePlan = null;
                $this->showForceReservationModal = false;

                session()->flash('error', $result['message'] ?? 'Impossibile calcolare un piano di riallocazione.');
                return;
            }

            // Caso 4B: mostriamo cosa verrà rubato e a chi.
            $this->forcePlan = $result['plan'];
            $this->showForceReservationModal = true;

            /* -------------------------------------------------------------
            * Se usi modali via JS, dispatchiamo un evento browser.
            * (Non è invasivo: se non lo ascolti, puoi usare direttamente $showForceReservationModal)
            *------------------------------------------------------------- */
            $this->dispatch('show-force-reservation-modal');

        } catch (\Throwable $e) {
            Log::error('[openForceReservation] ERROR', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Step 5: Conferma definitiva.
     * - Applica riallocazione (transaction + lock)
     * - Ritenta l'avanzamento di fase
     */
    public function commitForceReservation(): void
    {
        if (empty($this->forcePlan) || empty($this->forceMissingComponents)) {
            session()->flash('error', 'Nessun piano disponibile: ricalcola la riallocazione.');
            return;
        }

        try {
            /** @var OrderItem $item */
            $item = OrderItem::with('order')->findOrFail($this->advItemId);

            Log::info('[commitForceReservation] start', [
                'order_id'     => $item->order_id,
                'order_item_id'=> $item->id,
                'qty'          => $this->advQuantity,
            ]);

            /** @var ForceReservationExecutor $executor */
            $executor = app(ForceReservationExecutor::class);

            $result = $executor->execute(
                urgentItem: $item,
                moveQty: (float) $this->advQuantity,
                plan: (array) $this->forcePlan,
                user: auth()->user()
            );

            // Reset stato UI forzatura (chiudiamo definitivamente il flusso "force")
            $this->showForceReservationModal = false;
            $this->canForceReservation = false;
            $this->forcePlan = null;
            $this->forceMissingComponents = [];

            // Ritenta l’avanzamento, ora che le prenotazioni ci sono
            app(AdvanceOrderItemPhaseAction::class, [
                'item'       => OrderItem::findOrFail($this->advItemId),
                'quantity'   => $this->advQuantity,
                'user'       => auth()->user(),
                'fromPhase'  => ProductionPhase::from($this->phase),
                'isRollback' => false,
                'operator'   => $this->advOperator ? trim($this->advOperator) : null,
            ])->execute();

            $poNums = $result['procurement_po_numbers'] ?? collect();

            if ($poNums->isNotEmpty()) {
                session()->flash(
                    'success',
                    'Riallocazione completata e fase avanzata. Creati PO: ' . $poNums->implode(', ')
                );
            } else {
                session()->flash('success', 'Riallocazione completata e fase avanzata.');
            }

            $this->dispatch('close-row');
            $this->reset('advItemId','advMaxQty','advQuantity','advOperator');
            $this->resetPage();

        } catch (ValidationException $e) {
            // Messaggio business pulito
            session()->flash('error', $e->validator->errors()->first());
            Log::warning('[commitForceReservation] validation', ['errors' => $e->errors()]);

        } catch (\Throwable $e) {
            Log::error('[commitForceReservation] ERROR', [
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
     * Apre il modale di conferma stampa DDT.
     *
     * L'utente può:
     * - inserire/aggiornare le note dell'ordine
     * - sovrascrivere il totale finale dell'ordine
     *
     * @param int $orderItemId ID della riga ordine cliccata.
     */
    public function openDdtConfirm(int $orderItemId): void
    {
        try {
            if (! auth()->user()->can('stock.exit')) {
                throw ValidationException::withMessages([
                    'auth' => 'Non hai il permesso per generare il DDT.',
                ]);
            }

            /**
             * Carichiamo la riga con l'ordine padre e i riferimenti utili al modale.
             */
            $item = OrderItem::query()
                ->with([
                    'order.orderNumber',
                    'order.customer',
                    'order.occasionalCustomer',
                ])
                ->findOrFail($orderItemId);

            $order = $item->order;

            $this->ddtConfirmOrderItemId = $item->id;
            $this->ddtConfirmOrderId = $order?->id;
            $this->ddtConfirmOrderNumber = (string) ($order?->orderNumber?->number ?? $order?->id ?? '—');
            $this->ddtConfirmCustomer = $order?->customer?->company
                ?? $order?->occasionalCustomer?->company
                ?? '—';

            /**
             * Precompiliamo il modale con:
             * - note attuali dell'ordine
             * - prezzo unitario attuale della riga cliccata
             */
            $this->ddtConfirmNote = $order?->note;
            $this->ddtConfirmUnitPriceOverride = $item->unit_price !== null
                ? number_format((float) $item->unit_price, 2, '.', '')
                : null;

            $this->ddtConfirmOpen = true;
        } catch (\Throwable $e) {
            Log::error('[openDdtConfirm] ERROR', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Chiude il modale di conferma DDT e resetta il suo stato.
     */
    public function closeDdtConfirm(): void
    {
        $this->ddtConfirmOpen = false;

        $this->reset(
            'ddtConfirmOrderItemId',
            'ddtConfirmOrderId',
            'ddtConfirmOrderNumber',
            'ddtConfirmCustomer',
            'ddtConfirmNote',
            'ddtConfirmUnitPriceOverride'
        );
    }

    /**
     * Conferma la stampa del DDT:
     * - salva la nota su orders.note
     * - sovrascrive orders.total se viene inserito un totale finale override
     * - genera il DDT e apre la finestra di stampa
     */
    public function confirmDdtPrint(): void
    {
        $this->validate([
            'ddtConfirmNote' => ['nullable', 'string'],
            'ddtConfirmUnitPriceOverride' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'ddtConfirmNote' => 'note',
            'ddtConfirmUnitPriceOverride' => 'prezzo unitario finale',
        ]);

        try {
            if (! auth()->user()->can('stock.exit')) {
                throw ValidationException::withMessages([
                    'auth' => 'Non hai il permesso per generare il DDT.',
                ]);
            }

            if (! $this->ddtConfirmOrderItemId) {
                throw ValidationException::withMessages([
                    'ddt' => 'Riga ordine non valida per la generazione del DDT.',
                ]);
            }

            /**
             * Carichiamo la riga e l'ordine padre.
             */
            $item = OrderItem::query()
                ->with('order')
                ->findOrFail($this->ddtConfirmOrderItemId);

            $order = $item->order;

            if (! $order) {
                throw ValidationException::withMessages([
                    'ddt' => 'Ordine non trovato.',
                ]);
            }

            /**
             * Normalizzazione input:
             * - note vuote => null
             * - override vuoto => nessuna modifica del totale
             */
            $normalizedNote = $this->ddtConfirmNote !== null && trim($this->ddtConfirmNote) !== ''
                ? trim($this->ddtConfirmNote)
                : null;

            $normalizedUnitPriceOverride = $this->ddtConfirmUnitPriceOverride !== null
                && trim((string) $this->ddtConfirmUnitPriceOverride) !== ''
                    ? (float) $this->ddtConfirmUnitPriceOverride
                    : null;

            DB::transaction(function () use ($order, $normalizedNote): void {
                /**
                 * Salviamo solo la nota sull'header ordine.
                 *
                 * Il prezzo unitario override NON appartiene a orders:
                 * verrà invece scritto nello snapshot della riga DDT.
                 */
                $order->note = $normalizedNote;
                $order->save();
            });

            /**
             * Genera il DDT usando il flusso esistente.
             */
            $ddt = app(DdtService::class)->createForOrderItem(
                $item->id,
                auth()->user(),
                requestedQtyByItemId: null,
                unitPriceOverrideByItemId: $normalizedUnitPriceOverride !== null
                    ? [$item->id => $normalizedUnitPriceOverride]
                    : null
            );

            /**
             * URL firmato per la pagina di stampa.
             */
            $printUrl = URL::temporarySignedRoute(
                'warehouse.ddt.print',
                now()->addMinutes(5),
                ['ddt' => $ddt->id]
            );

            $this->dispatch('open-print-window', url: $printUrl);

            $this->closeDdtConfirm();

            session()->flash('success', "DDT nr. {$ddt->number} generato. Apertura stampa…");
        } catch (\Throwable $e) {
            Log::error('[confirmDdtPrint] ERROR', [
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Compatibilità: il flusso DDT passa ora dal modale di conferma.
     *
     * @param int $orderItemId ID della riga ordine cliccata.
     */
    public function printDdt(int $orderItemId): void
    {
        $this->openDdtConfirm($orderItemId);
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
