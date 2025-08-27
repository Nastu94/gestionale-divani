{{-- resources/views/pages/warehouse/entry.blade.php --}}

<x-app-layout>
    {{-- HEADER identico alle altre viste --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Ricevimento merce') }}
            </h2>
            <x-dashboard-tiles />
        </div>

        {{-- Flash messages --}}
        @foreach (['success' => 'green', 'error' => 'red'] as $k => $c)
            @if (session($k))
                <div  x-data="{ show:true }"
                      x-init="setTimeout(()=>show=false,10000)"
                      x-show="show"
                      x-transition.opacity.duration.500ms
                      class="bg-{{ $c }}-100 border border-{{ $c }}-400 text-{{ $c }}-700
                             px-4 py-3 rounded relative mt-2">
                    <i class="fas {{ $k=='success' ? 'fa-check-circle':'fa-exclamation-triangle' }} mr-2"></i>
                    <span>{{ session($k) }}</span>
                </div>
            @endif
        @endforeach
    </x-slot>
    {{-- STILE per la tabella --}}
    <style>
        .group-start td {
            border-top: 1px solid #e5e7eb;   /* gray-200 */
        }
    </style>

    <div class="py-6">
        <div  x-data="entryCrud()"
            @open-register.window="openModal($event.detail)"
            @open-new-entry.window="openModal(null)"
            class="max-w-full mx-auto sm:px-6 lg:px-8">

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                {{-- ╔════════════════════════ Pulsanti header ═══════════════════════╗ --}}
                <div class="flex justify-end m-2 p-2">

                    {{-- Pulsante NUOVO (crea ricevimento + ordine) --}}
                    @can('stock.entry')
                        <button  @click="$dispatch('open-new-entry')"
                                class="inline-flex items-center m-2 px-3 py-1.5 bg-purple-600 rounded-md
                                        text-xs font-semibold text-white uppercase hover:bg-purple-500
                                        focus:outline-none focus:ring-2 focus:ring-purple-300 transition">
                            <i class="fas fa-plus mr-1"></i> Nuovo
                        </button>
                    @endcan

                    {{-- Pulsante Estendi/Comprimi --}}
                    <button  @click="extended = !extended"
                            class="inline-flex items-center m-2 px-3 py-1.5 bg-indigo-600 rounded-md
                                    text-xs font-semibold text-white uppercase hover:bg-indigo-500
                                    focus:outline-none focus:ring-2 focus:ring-indigo-900 transition">
                        <i class="fas p-1" :class="extended ? 'fa-compress' : 'fa-expand'"></i>
                        <span x-text="extended ? 'Comprimi tabella' : 'Estendi tabella'"></span>
                    </button>
                </div>

                {{-- TABELLONE --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-2 text-left">#</th>
                                <x-th-menu 
                                    field="order_number"            
                                    label="Nr. Ordine"
                                    :sort="$sort" 
                                    :dir="$dir" 
                                    :filters="$filters" 
                                    reset-route="stock-movements-entry.index" 
                                    align="left" 
                                />
                                <x-th-menu 
                                    field="supplier"            
                                    label="Fornitore"
                                    :sort="$sort" 
                                    :dir="$dir" 
                                    :filters="$filters" 
                                    reset-route="stock-movements-entry.index" 
                                />
                                {{-- Colonne nascoste in view compatta --}}
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left">Data ordine</th>
                                <x-th-menu 
                                    field="delivery_date"
                                    label="Data consegna"
                                    :sort="$sort" 
                                    :dir="$dir" 
                                    :filters="$filters" 
                                    reset-route="stock-movements-entry.index" 
                                />
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left">Data registrazione</th>
                                <th class="px-6 py-2 text-left">N. Bolla</th>
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($supplierOrders as $order)
                                @php
                                    $canReceipt = auth()->user()->can('stock.entry');
                                    $canView = auth()->user()->can('orders.supplier.view');

                                    $canToggle = $canReceipt || $canView;
                                @endphp

                                {{-- RIGA PRINCIPALE --}}
                                <tr  
                                    @if($canToggle)
                                        @click="openId = (openId === {{ $order->id }} ? null : {{ $order->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $order->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    <td class="px-6 py-2">{{ $loop->iteration + ($supplierOrders->currentPage()-1)*$supplierOrders->perPage() }}</td>
                                    <td class="px-6 py-2">{{ $order->number ?? $order->id }}</td>
                                    <td class="px-6 py-2">{{ $order->supplier->name }}</td>

                                    {{-- Colonne condizionali --}}
                                    <td x-show="extended" x-cloak class="px-6 py-2">
                                        {{ optional($order->ordered_at)->format('d/m/Y') }}
                                    </td>

                                    <td class="px-6 py-2">
                                        {{ optional($order->delivery_date)->format('d/m/Y') }}
                                    </td>

                                    <td x-show="extended" x-cloak class="px-6 py-2">
                                        {{ optional($order->registration_date)->format('d/m/Y') ?? '-' }}
                                    </td>

                                    <td class="px-6 py-2">{{ $order->bill_number ?? '-' }}</td>
                                </tr>

                                {{-- RIGA CRUD: Visualizza / Registra --}}
                                <tr x-show="openId === {{ $order->id }}" x-cloak>
                                    <td :colspan="extended ? 7 : 5" class="px-6 py-3 bg-gray-200 dark:bg-gray-700">
                                        <div class="flex items-center space-x-4 text-xs">
                                            {{-- Pulsante Visualizza (sidebar) --}}
                                            @can('orders.supplier.view')
                                                <button type="button"
                                                        @click="$dispatch('open-lines', {
                                                            orderId: {{ $order->id }},
                                                            orderNumber: '{{ $order->number }}'
                                                        })"
                                                        class="inline-flex items-center hover:text-indigo-700">
                                                    <i class="fas fa-eye mr-1"></i> Visualizza
                                                </button>
                                            @endcan

                                            {{-- Pulsante Registra --}}
                                            @can('stock.entry')
                                                @if( !($order->registration_date && $order->bill_number) )
                                                    <button type="button"
                                                            @click="openModal({
                                                                order_id        : {{ $order->id }},
                                                                order_number_id : {{ $order->order_number_id }},
                                                                order_number    : @js($order->number),

                                                                delivery_date   : @js(optional($order->delivery_date)->format('Y-m-d')),
                                                                bill_number     : @js($order->bill_number),

                                                                supplier_id    : {{ $order->supplier_id }},
                                                                supplier_name  : @js($order->supplier->name),
                                                                supplier_email : @js($order->supplier->email),
                                                                vat_number     : @js($order->supplier->vat_number),
                                                                address        : @js($order->supplier->address),

                                                                /* righe dell’ordine (solo i campi necessari) */
                                                                items: @js(
                                                                    $order->items->map(function ($i) use ($order) {

                                                                        // tutti i lotti dell’ordine relativi a **quel componente**
                                                                        $lots = $order->stockLevelLots
                                                                            ->filter(fn($lot) =>
                                                                                optional($lot->stockLevel)->component_id === $i->component_id
                                                                            )
                                                                            ->map(fn($lot) => [
                                                                                'code'     => $lot->internal_lot_code,
                                                                                'supplier' => $lot->supplier_lot_code,
                                                                                'qty'      => $lot->received_quantity,
                                                                            ])
                                                                            ->values();   // rimuove eventuali chiavi sparse

                                                                        return [
                                                                            'id'          => $i->id,
                                                                            'code'        => $i->component->code,
                                                                            'name'        => $i->component->description,
                                                                            'qty_ordered' => $i->quantity,
                                                                            'lots'        => $lots,
                                                                            'unit'        => $i->component->unit_of_measure,
                                                                        ];
                                                                    })
                                                                ),
                                                            })"
                                                            class="inline-flex items-center hover:text-green-700">
                                                        <i class="fas fa-arrow-down mr-1"></i> Registra ricevimento
                                                    </button>
                                                @endif  
                                            @endcan

                                            {{-- Pulsante Crea Shortfall --}}
                                            @can('orders.supplier.create')
                                                @if(($order->is_registered ?? false) && !($order->has_shortfall ?? false) && ($order->needs_shortfall ?? false))
                                                    <button
                                                        type="button"
                                                        class="inline-flex items-center hover:text-red-700"
                                                        x-data
                                                        x-on:click.prevent="
                                                            $dispatch('loading', { on: true });
                                                            fetch('{{ route('orders.supplier.shortfall.create') }}', {
                                                                method: 'POST',
                                                                headers: {
                                                                    'Content-Type': 'application/json',
                                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                                    'Accept': 'application/json'
                                                                },
                                                                body: JSON.stringify({ order_id: {{ $order->id }} })
                                                            })
                                                            .then(r => r.json())
                                                            .then(data => {
                                                                $dispatch('loading', { on: false });
                                                                if (data?.status === 'ok') {
                                                                    alert(data.message ?? 'Shortfall creato.');
                                                                    window.location.reload();
                                                                } else {
                                                                    alert(data?.message ?? 'Operazione non completata.');
                                                                }
                                                            })
                                                            .catch(() => {
                                                                $dispatch('loading', { on: false });
                                                                alert('Errore di rete.');
                                                            })
                                                        "
                                                        title="Crea Shortfall per questo ordine fornitore"
                                                    >
                                                        <i class="fa fa-life-ring mr-1"></i> Crea Shortfall
                                                    </button>
                                                @endif
                                            @endcan

                                            {{-- Pulsante Modifica (solo se già registrato) --}}
                                            @can('stock.entryEdit')
                                                @if($order->registration_date && $order->bill_number)
                                                    <button type="button"
                                                            class="inline-flex items-center hover:text-amber-400"
                                                        @click="$store.entryModal.openEdit({
                                                            order_id        : {{ $order->id }},
                                                            order_number_id : {{ $order->order_number_id }},
                                                            order_number    : @js($order->number),

                                                            delivery_date   : @js(optional($order->delivery_date)->format('Y-m-d')),
                                                            bill_number     : @js($order->bill_number),

                                                            supplier_id    : {{ $order->supplier_id }},
                                                            supplier_name  : @js($order->supplier->name),
                                                            supplier_email : @js($order->supplier->email),
                                                            vat_number     : @js($order->supplier->vat_number),
                                                            address        : @js($order->supplier->address),

                                                            items: @js($order->items->map(fn ($i) => [
                                                                'id'          => $i->id,
                                                                'code'        => $i->component->code,
                                                                'name'        => $i->component->description,
                                                                'qty_ordered' => $i->quantity,
                                                                'lots'        => $order->stockLevelLots
                                                                                    ->filter(fn($lot) =>
                                                                                        optional($lot->stockLevel)->component_id === $i->component_id
                                                                                    )
                                                                                    ->map(fn($lot) => [
                                                                                        'id'       => $lot->id,          // ↑ ci servirà in PATCH
                                                                                        'code'     => $lot->internal_lot_code,
                                                                                        'supplier' => $lot->supplier_lot_code,
                                                                                        'qty'      => $lot->received_quantity,
                                                                                    ])
                                                                                    ->values(),
                                                                'unit'        => $i->component->unit_of_measure,
                                                            ])),
                                                        })">
                                                        <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                                    </button>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- PAGINAZIONE --}}
                <div class="mt-4 px-6 py-2">
                    {{ $supplierOrders->links('vendor.pagination.tailwind-compact') }}
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL per registrazione ricevimento merce --}}
    <div  x-show="$store.entryModal.show" x-cloak
      class="fixed inset-0 z-50 flex items-center justify-center">
        <div  class="absolute inset-0 bg-black opacity-75"></div>

        <div class="relative z-10 w-full max-w-5xl">
            {{-- componente Blade riutilizzabile --}}
            <x-stock-entry-modal />
        </div>
    </div>

    {{-- Sidebar Righe Ordine --}}
    <div x-show="$store.orderSidebar.open"
        x-cloak
        class="fixed inset-0 z-50 flex justify-end">

        {{-- backdrop --}}
        <div class="flex-1 bg-black/50" @click="$store.orderSidebar.close()"></div>

        {{-- pannello --}}
        <div class="w-full max-w-lg bg-white dark:bg-gray-900 shadow-xl overflow-y-auto"
            x-transition:enter="transition transform duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:leave="transition transform duration-300"
            x-transition:leave-end="translate-x-full">

            <div class="p-6 border-b flex justify-between items-center">
                <h3 class="text-lg font-semibold">
                    Righe ordine #<span x-text="$store.orderSidebar.number"></span>
                </h3>
                <button @click="$store.orderSidebar.close()">
                    <i class="fas fa-times text-gray-600"></i>
                </button>
            </div>

            {{-- overlay spinner --}}
            <div x-show="$store.orderSidebar.loading"
                x-transition.opacity
                x-cloak
                class="absolute inset-0 flex items-center justify-center bg-white/70">
                <i class="fas fa-circle-notch fa-spin text-3xl text-gray-600"></i>
            </div>

            <div x-show="$store.orderSidebar.lines.length" x-cloak class="p-4">
                <table class="w-full text-xs border divide-y">
                    <thead class="bg-gray-100 dark:bg-gray-800 uppercase">
                        <tr>
                            <th class="px-2 py-1">Codice</th>
                            <th class="px-2 py-1">Componente</th>
                            <th class="px-2 py-1 text-right w-14">Ord.</th>
                            <th class="px-2 py-1 text-right w-14">Ric.</th>
                            <th class="px-2 py-1">Lotto&nbsp;forn.</th>
                            <th class="px-2 py-1">Lotto&nbsp;int.</th>
                            <th class="px-2 py-1 w-10 whitespace-nowrap">U. M.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(l, index) in $store.orderSidebar.lines" :key="index">
                            <tr :class="index === 0 || $store.orderSidebar.lines[index-1].code !== l.code
                                ? 'group-start' : ''">
                                <!-- codice -->
                                <td class="px-2 py-1"
                                    x-text="index === 0 || $store.orderSidebar.lines[index-1].code !== l.code ? l.code : ''">
                                </td>

                                <!-- descrizione -->
                                <td class="px-2 py-1"
                                    x-text="index === 0 || $store.orderSidebar.lines[index-1].code !== l.code ? l.desc : ''">
                                </td>

                                <!-- Q. ordinata -->
                                <td class="px-2 py-1 text-right"
                                    x-text="index === 0 || $store.orderSidebar.lines[index-1].code !== l.code ? l.qty : ''">
                                </td>

                                <!-- Q. ricevuta (per quel lotto) -->
                                <td class="px-2 py-1 text-right" x-text="l.qty_received"></td>

                                <!-- lotto fornitore -->
                                <td class="px-2 py-1" x-text="l.lot_supplier ?? '—'"></td>

                                <!-- lotto interno -->
                                <td class="px-2 py-1" x-text="l.internal_lot ?? '—'"></td>

                                <!-- U.M. -->
                                <td class="px-2 py-1 uppercase"
                                    x-text="index === 0 || $store.orderSidebar.lines[index-1].code !== l.code ? l.unit : ''">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- COMPONENTE ALPINE --}}
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                /* ───── STORE GLOBALE per il modale ───── */
                Alpine.store('entryModal', {
                    
                    // visibilità
                    show  : false,
                    isNew : true,
                    newLotCode : false,

                    /*variabili per la modifica */
                    editMode : false,          // true se stiamo modificando un carico già salvato
                    originalLotId : null,      // id del lotto che stiamo editando

                    /* stato ordine appena creato ma NON ancora salvato */
                    orderSaved : false,

                    /* ritorna true quando tutti i campi di testata sono completi
                          (abbrevia le espressioni nelle x-show / x-bind) */
                    get headerComplete() {
                        return  this.formData.order_number   &&
                                this.formData.delivery_date  &&
                                this.formData.supplier_id    &&
                                this.formData.bill_number;
                    },

                    // cache righe tabella (reattivo)
                    items : [],

                    /* dati header ---------------------------------------------------- */
                    formData: {
                        order_id      : null,
                        supplier_id   : null,
                        supplier_name : '',
                        order_number_id : null,
                        order_number    : '',
                        delivery_date : '',
                        bill_number   : '',
                    },

                    /* autocomplete fornitore ---------------------------------------- */
                    supplierSearch  : '',
                    supplierOptions : [],
                    selectedSupplier: null,

                    /* ------------------------- AUTOCOMPONENT --------------------------- */
                    componentSearch   : '',   
                    componentOptions  : [],   
                    selectedComponent : null, 

                    /* === stato corrente della riga ================================== */
                    currentRow: {
                        id             : null,           // id riga ordine, o null se manuale
                        component      : '',
                        component_code : '',
                        unit           : '',
                        lot_supplier   : '',

                        // sempre presente ↓ (almeno un oggetto vuoto)
                        lots           : [{ code:'', supplier:'', qty:'' }],
                    },

                    /* ═══════════════ METODI ═══════════════ */

                    // Open modal (data = payload dal pulsante, oppure null se “Nuovo”)
                    open(data = null, mode = 'create') {
                        /* 1. flag ------------------------------------------------------- */
                        this.editMode   = mode === 'edit';
                        this.isNew      = !this.editMode && !data;
                        this.orderSaved = !this.isNew;
                        console.log('Data received:', data);
                        this.newLotCode = data === null ? true : false; // reset flag per nuovo lotto

                        /* 2. reset autocomplete supplier ------------------------------- */
                        this.supplierSearch  = '';
                        this.supplierOptions = [];

                        /* 3. header ----------------------------------------------------- */
                        this.formData = data ?? {
                            order_id      : null,
                            supplier_id   : null,
                            supplier_name : '',
                            order_number  : '',
                            delivery_date : '',
                            bill_number   : '',
                        };

                        /* 4. items (cache > payload) ----------------------------------- */
                        const fromCache = data?.order_id
                            ? Alpine.store('orderCache')[data.order_id]
                            : null;

                        this.items = fromCache ?? (data?.items || []);

                        /* 4.a → assegno _oldQty e _oldSupplier */
                        this.items.forEach(item => {
                            if (!Array.isArray(item.lots)) {
                                item.lots = Object.values(item.lots || {});
                            }
                            item.lots.forEach(lot => {
                                lot._oldQty      = parseFloat(lot.qty);   // ← snapshot quantità originale
                                lot._oldSupplier = lot.supplier || '';    // ← snapshot lotto fornitore
                            });
                        });

                        /* 5. progressivo ordine ---------------------------------------- */
                        if (this.isNew) {
                            this.reserveOrderNumber();
                        } else {
                            this.formData.order_number_id = data.order_number_id ?? null;
                            this.formData.order_number    = data.order_number    ?? '';
                        }

                        /* 6. supplier pre-compilato ------------------------------------ */
                        this.selectedSupplier = data?.supplier_id
                            ? {
                                id         : data.supplier_id,
                                name       : data.supplier_name,
                                email      : data.supplier_email,
                                vat_number : data.vat_number,
                                address    : data.address ?? {
                                    via:'', city:'', postal_code:'', country:''
                                },
                            }
                            : null;

                        /* 7. mostra modale --------------------------------------------- */
                        this.show = true;
                    },

                    // Open modal per modifica ricevimento esistente
                    openEdit(payload) {
                        this.open(payload, 'edit');
                    },

                    /* Chiude il modale e resetta lo stato */
                    close() {
                        /* reset autocomplete fornitore */
                        this.supplierSearch   = '';
                        this.supplierOptions  = [];
                        this.selectedSupplier = null;

                        /* reset autocomplete componente */
                        this.componentSearch  = '';
                        this.componentOptions = [];
                        this.selectedComponent = null;

                        /* reset riga corrente */
                        this.resetRow();          // usa già la funzione che svuota currentRow

                        /* reset header */
                        this.formData = {
                            order_id         : null,
                            supplier_id      : null,
                            supplier_name    : '',
                            order_number_id  : null,
                            order_number     : '',
                            delivery_date    : '',
                            bill_number      : '',
                        };

                        /* reset flag/modal */
                        this.isNew = true;
                        this.show  = false;
                    },

                    /* ─────── ricerca fornitori (solo create) ─────── */
                    async searchSuppliers() {
                        if (this.supplierSearch.trim().length < 2) { this.supplierOptions = []; return; }

                        try {
                            const r = await fetch(
                                `/suppliers/search?q=${encodeURIComponent(this.supplierSearch.trim())}`,
                                { headers: { Accept: 'application/json' }, credentials: 'same-origin' }
                            );
                            if (!r.ok) throw new Error(r.status);
                            this.supplierOptions = await r.json();
                        } catch { this.supplierOptions = []; }
                    },
                    /* seleziona fornitore dalla lista */
                    selectSupplier(o) {
                        this.selectedSupplier          = o;
                        this.formData.supplier_id      = o.id;
                        this.formData.supplier_name    = o.name;
                        this.supplierOptions           = [];
                    },
                    /* cancella fornitore selezionato */
                    clearSupplier() {
                        this.selectedSupplier       = null;
                        this.formData.supplier_id   = null;
                        this.formData.supplier_name = '';
                        this.supplierSearch         = '';
                    },

                    /* ricerca componenti --------------------------- */
                    async searchComponents() {
                        if (this.componentSearch.trim().length < 2) { this.componentOptions = []; return; }

                        try {
                            const r = await fetch(`/components/search?q=${encodeURIComponent(this.componentSearch.trim())}` +
                                                (this.formData.supplier_id ? `&supplier_id=${this.formData.supplier_id}` : ''),
                                                { headers:{Accept:'application/json'}, credentials:'same-origin' });
                            if (!r.ok) throw new Error(r.status);
                            this.componentOptions = await r.json();
                        } catch { this.componentOptions = []; }

                        this.newLotCode = true;  // reset flag per nuovo lotto
                    },

                    /* seleziona componente dalla lista */
                    selectComponent(o) {
                        this.selectedComponent             = o;
                        this.currentRow.component_code     = o.code;
                        this.currentRow.component          = `${o.description}`;
                        this.currentRow.unit               = o.unit_of_measure ?? o.unit;
                        this.componentOptions              = [];
                    },

                    /* cancella componente selezionato */
                    clearComponent() {
                        this.selectedComponent   = null;
                        this.componentSearch     = '';
                        this.currentRow.component         = '';
                        this.currentRow.component_code    = '';
                        this.currentRow.unit              = '';
                    },

                    /* ---------- DETTAGLI fornitore (modalità “Registra”) ---------- */
                    async fetchSupplierDetails(id) {
                        try {
                            const r = await fetch(`/suppliers/${id}`,
                                                { headers:{Accept:'application/json'}, credentials:'same-origin' });
                            if (!r.ok) throw new Error(r.status);
                            this.selectedSupplier = await r.json();     // contiene name, email, vat, address…
                        } catch {
                            /* fallback: almeno nome/ID, se l’API fallisce */
                            this.selectedSupplier = {
                                id,
                                name: this.formData.supplier_name,
                                email: '',
                                vat_number: '',
                                address: { via:'', city:'', postal_code:'', country:'' },
                            };
                        }
                    },

                    /* ---------- PRENOTA PROGRESSIVO ---------- */
                    async reserveOrderNumber() {
                        try {
                            const r = await fetch('/order-numbers/reserve', {
                                method : 'POST',
                                headers: {
                                    'Accept'       : 'application/json',
                                    'Content-Type' : 'application/json',
                                    'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                                },
                                credentials : 'same-origin',
                                body : JSON.stringify({ type: 'supplier' })   // stesso tipo degli ordini fornitore
                            });
                            if (!r.ok) throw new Error(r.status);
                            const j = await r.json();
                            this.formData.order_number_id = j.id;
                            this.formData.order_number    = j.number;
                        } catch {
                            this.formData.order_number_id = null;
                            this.formData.order_number    = '—';
                        }
                    },

                    /* ─────── CRUD per le righe della tabella ─────── */
                    loadRow(id) {

                        const r = this.items.find(i => i.id === id);
                        if (!r) return;

                        /* 1‧ normalizza lots in ARRAY e clona -------------------------- */
                        const lotsCloned = (
                            Array.isArray(r.lots) ? r.lots : Object.values(r.lots || {})
                        ).map(l => ({
                            ...JSON.parse(JSON.stringify(l)),    // deep-clone
                            _oldQty      : parseFloat(l.qty),    // 👈 snapshot quantità originale
                            _oldSupplier : l.supplier || '',     // 👈 snapshot lotto fornitore
                        }));

                        /* 2‧ se righe senza lotto, prepariamo placeholder -------------- */
                        const lotsSafe = lotsCloned.length
                            ? lotsCloned
                            : [{
                                code        : '',
                                supplier    : '',
                                qty         : '',
                                _oldQty      : 0,
                                _oldSupplier : '',
                            }];

                        /* 3‧ popola currentRow ---------------------------------------- */
                        this.currentRow = {
                            id             : r.id,
                            component      : `${r.code} – ${r.name}`,
                            component_code : r.code,
                            qty_ordered    : r.qty_ordered,
                            unit           : r.unit,
                            lot_supplier   : lotsSafe[0].supplier,
                            lots           : lotsSafe,
                        };

                        console.log('Loaded row:', this.currentRow);

                        if(this.currentRow.lots[0].code === ''){
                            this.newLotCode = true;
                        }
                    },

                    // resetta la riga corrente a uno stato vuoto
                    resetRow() {
                        this.currentRow = {
                            id : null, 
                            component:'', 
                            component_code:'', 
                            qty_ordered:'',
                            qty_received:'', 
                            unit:'', 
                            lot_supplier:'', 
                            lots: [{ code:'', supplier:'', qty:'' }],
                        };
                    },

                    /* aggiungi/rimuovi lotti -------------------------------- */
                    // aggiunge un lotto vuoto alla riga corrente
                    addLot() {
                        // assicura che lots esista
                        if (!Array.isArray(this.currentRow.lots)) {
                            this.currentRow.lots = [];
                        }
                        this.currentRow.lots.push({ code:'', supplier:'', qty:'' });
                        this.newLotCode = true;  // reset flag per nuovo lotto
                    },

                    // rimuove un lotto dalla riga corrente
                    removeLot(idx) {
                        if (this.currentRow.lots && this.currentRow.lots.length > 1) {
                            this.currentRow.lots.splice(idx, 1);
                        }
                    },

                    // genera un lotto casuale per l'indice specificato
                    generateLot(idx) {
                        fetch('/lots/reserve', {
                            method:'POST',
                            headers:{
                                'Accept':'application/json',
                                'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
                            },
                            credentials:'same-origin'
                        })
                        .then(r => r.json())
                        .then(j => this.currentRow.lots[idx].code = j.next)
                        .catch(() => alert('Impossibile generare lotto.'));
                    },

                    /* validazione prima del fetch ---------------------------------------- */
                    validateRow() {
                        if (!this.currentRow.component_code) return 'Seleziona un componente.';
                        const invalid = this.currentRow.lots.some(l => !l.code || !l.qty);
                        if (invalid) return 'Completa codice e quantità per ogni lotto.';
                        return null;
                    },

                    /* Salva testata ordine fornitore (crea o aggiorna) */
                    saveOrderHeader: async function () {

                        /* 1 – pre-check rapidi */
                        if (!this.headerComplete) {
                            alert('Compila tutti i dati di testata prima di salvare.');
                            return;
                        }

                        /* 2 – payload */
                        const payload = {
                            order_number_id : this.formData.order_number_id,
                            supplier_id     : this.selectedSupplier?.id,
                            delivery_date   : this.formData.delivery_date,
                            bill_number     : this.formData.bill_number,
                        };

                        /* 3 – POST → /orders/supplier/by-registration */
                        try {
                            const resp = await fetch('{{ url('orders/supplier/by-registration') }}', {
                                method : 'POST',
                                headers: {
                                    'Accept'       : 'application/json',
                                    'Content-Type' : 'application/json',
                                    'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                                },
                                credentials: 'same-origin',
                                body : JSON.stringify(payload)
                            });

                            const j = await resp.json();
                            if (!resp.ok || !j.success) throw new Error(j.message || 'HTTP ' + resp.status);

                            alert('Ordine #' + j.number + ' salvato! Ora puoi registrare i componenti.');

                            /* 4 – aggiorna stato front-end */
                            this.formData.order_id = j.id;          // servirà ai call successivi
                            this.orderSaved        = true;          // sblocca la sezione dettagli

                        } catch (e) {
                            alert('Errore salvataggio testata: ' + e.message);
                        }
                    },

                    /* Salva riga corrente (crea o aggiorna) */
                    async saveRow() {

                        /* 0‧ validazione base già esistente --------------------------- */
                        const err = this.validateRow();
                        if (err) { alert(err); return; }

                        /* 1‧ controllo duplicati lotto nella stessa riga ------------- */
                        const localDup = new Set();
                        const dup = this.currentRow.lots.some(l => {
                            if (localDup.has(l.code)) return true;   // trovato doppione
                            localDup.add(l.code);
                            return false;
                        });

                        if (dup) {
                            alert('Hai inserito lo stesso lotto interno due volte nella stessa riga.');
                            return;      // blocca il salvataggio
                        }

                        /* 2‧ individua (una sola volta) la riga nella tabella ---------- */
                        let idx = this.items.findIndex(i => i.code === this.currentRow.component_code);
                        let rowCreated = false;   // flag se dobbiamo creare la riga fuori-ordine

                        /* 3‧ ciclo sui lotti da registrare ----------------------------- */
                        for (const lot of this.currentRow.lots) {

                            const r = await fetch('{{ route('stock-movements-entry.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Accept'       : 'application/json',
                                    'Content-Type' : 'application/json',
                                    'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    order_id         : this.formData.order_id,
                                    component_code   : this.currentRow.component_code,
                                    qty_received     : lot.qty,
                                    lot_supplier     : lot.supplier,
                                    internal_lot_code: lot.code,
                                })
                            });

                            const resp = await r.json();

                            if (!r.ok) {
                                /*  ▼▼ nuovo ramo per il blocco short-fall ▼▼  */
                                if (resp.blocked === 'shortfall') {
                                    alert(resp.message);          // o toast
                                    this.clearComponent();
                                    this.resetRow();  // reset riga corrente
                                    return;
                                }
                                /*  ▲▲ altrimenti continua col ramo errori già presente ▲▲  */
                                throw new Error(resp.message || 'Errore');
                            }

                            if (!resp.success) {
                                alert(resp.message || 'Errore sconosciuto');    // exit immediato
                                return;
                            }

                            /* 4‧ aggiorna la tabella reattiva -------------------------- */
                            if (idx !== -1) {
                                /* riga esiste già → pushiamo il nuovo lotto */
                                this.items[idx].lots.push({
                                    id        : resp.lot.id,
                                    code      : resp.lot.internal_lot_code,
                                    supplier  : resp.lot.supplier_lot_code,
                                    qty       : resp.lot.quantity,
                                    _oldQty      : parseFloat(resp.lot.quantity),   // snapshot
                                    _oldSupplier : resp.lot.supplier_lot_code,      // snapshot
                                });
                            } else {
                                /* prima iterazione: riga “fuori ordine” */
                                if (!rowCreated) {
                                    this.items.push({
                                        id          : null,
                                        code        : this.currentRow.component_code,
                                        name        : this.currentRow.component,
                                        qty_ordered : 0,
                                        lots        : [],
                                        unit        : this.currentRow.unit,
                                    });
                                    idx = this.items.length - 1;
                                    rowCreated = true;
                                }
                                this.items[idx].lots.push({
                                    id        : resp.lot.id,
                                    code      : resp.lot.internal_lot_code,
                                    supplier  : resp.lot.supplier_lot_code,
                                    qty       : resp.lot.quantity,
                                    _oldQty      : parseFloat(resp.lot.quantity),   // snapshot
                                    _oldSupplier : resp.lot.supplier_lot_code,      // snapshot
                                });
                            }
                        }

                        if(this.formData.order_id){
                            Alpine.store('orderCache')[this.formData.order_id] =
                                JSON.parse(JSON.stringify(this.items));
                        }

                        /* 5‧ reset del form riga dopo ciclo completo ------------------- */
                        this.clearComponent();
                        this.resetRow();
                    },

                    /* Aggiorna (o aggiunge) uno o più lotti della riga corrente */
                    async updateEntry() {

                        /* 0‧ costruisci due array: toCreate e toUpdate ---------------- */
                        const toCreate = [];
                        const toUpdate = [];
                        let hasNegativeDelta = false;

                        this.currentRow.lots.forEach(l => {

                            /* nuovo lotto (non ha id) ⇒ sempre da creare */
                            if (!l.id) {
                                toCreate.push({
                                    order_id         : this.formData.order_id,      // può essere null
                                    component_code   : this.currentRow.component_code,
                                    qty_received     : l.qty,
                                    lot_supplier     : l.supplier,
                                    internal_lot_code: l.code,
                                });
                                return;
                            }

                            /* lotto esistente: verifica se qualcosa è cambiato         */
                            const changed = (l.qty != l._oldQty || l.supplier != l._oldSupplier);
                            if (changed) {
                                toUpdate.push({
                                    id           : l.id,
                                    qty          : l.qty,
                                    lot_supplier : l.supplier,
                                });
                                if (parseFloat(l.qty) < parseFloat(l._oldQty)) hasNegativeDelta = true;
                            }
                        });

                        if (!toCreate.length && !toUpdate.length) {
                            alert('Nessuna modifica da salvare.');
                            return;
                        }

                        // chiedi se creare shortfall solo se c’è almeno un delta negativo
                        let allowShortfall = false;
                        if (hasNegativeDelta) {
                            allowShortfall = confirm(
                                'Stai riducendo quantità già registrate.\n' +
                                'Vuoi generare automaticamente un ordine di recupero se necessario?'
                            );
                        }

                        /* helper fetch JSON ------------------------------------------ */
                        const fetchJson = async (url, method, body) => {
                            const r = await fetch(url, {
                                method,
                                headers : {
                                    'Accept'       : 'application/json',
                                    'Content-Type' : 'application/json',
                                    'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body : JSON.stringify(body),
                            });
                            const j = await r.json();
                            if (!r.ok || !j.success) {
                                throw new Error(j.message || 'Errore server ('+r.status+')');
                            }
                            return j;
                        };

                        try {

                            /* CREA i lotti nuovi ---------------------------------- */
                            for (const body of toCreate) {
                                await fetchJson('{{ route('stock-movements-entry.store') }}', 'POST', body);
                            }

                            /* PATCH sui lotti esistenti (se ne ho) ---------------- */
                            let patchResp = { updated: [] };
                            if (toUpdate.length) {
                                patchResp = await fetchJson(
                                    '{{ route('stock-movements-entry.update') }}',
                                    'PATCH',
                                    { lots: toUpdate, allow_shortfall: allowShortfall }
                                );
                            }

                            /* sincronia dati nel front-end ------------------------ */
                            patchResp.updated.forEach(u => {

                                /* currentRow */
                                const crLot = this.currentRow.lots.find(l => l.id === u.id);
                                if (crLot) {
                                    crLot.qty          = u.qty;
                                    crLot.supplier     = u.lot_supplier;
                                    crLot._oldQty      = u.qty;
                                    crLot._oldSupplier = u.lot_supplier;
                                }

                                /* tabella riepilogo */
                                this.items.forEach(it => {
                                    const lot = it.lots.find(l => l.id === u.id);
                                    if (lot) {
                                        lot.qty          = u.qty;
                                        lot.supplier     = u.lot_supplier;
                                        lot._oldQty      = u.qty;
                                        lot._oldSupplier = u.lot_supplier;
                                    }
                                });
                            });

                            /* feedback utente ------------------------------------ */
                            if (Array.isArray(patchResp.follow_up_orders) && patchResp.follow_up_orders.length) {
                                const nums = patchResp.follow_up_orders.map(o => `#${o.number}`).join(', ');
                                alert('Aggiornamento effettuato. Creati ordini di recupero: ' + nums + '.');
                            } else if (patchResp.shortfall_blocked === 'no_permission') {
                                alert(
                                    'Aggiornamento effettuato.\n' +
                                    'Servirebbe un ordine di recupero, ma non hai i permessi per crearlo.\n' +
                                    'Avvisa gli acquisti per generarlo.'
                                );
                            } else if (patchResp.shortfall_needed === true && allowShortfall === false) {
                                alert(
                                    'Aggiornamento effettuato.\n' +
                                    'Le quantità risultano mancanti, ma non hai richiesto di generare ordini di recupero.'
                                );
                            } else if (toUpdate.length) {
                                alert('Lotti aggiornati con successo!');
                            } else {
                                alert('Lotti creati con successo!');
                            }

                            /* 5‧ ricarica per aggiornare cache ---------------------- */
                            location.reload();

                        } catch (e) {
                            alert('Errore aggiornamento: ' + e.message);
                        }
                    },

                    /* Salva registrazione ordine fornitore */
                    async saveRegistration() {

                        /* 1a ‧ controlla righe con lotti “incompleti”  (bloccante) ---- */
                        const hasInvalidLot = this.items.some(i =>
                            (i.lots || []).some(l =>
                                !l.code || l.code.trim() === '' ||
                                !l.qty  || parseFloat(l.qty) <= 0
                            )
                        );

                        if (hasInvalidLot) {
                            alert('Alcuni lotti hanno codice o quantità non validi: correggi prima di salvare.');
                            return;
                        }

                        /* 1b ‧ righe senza alcun lotto  (mancata consegna) ------------ */
                        const hasShortfall = this.items.some(i => {
                            const tot = (i.lots || []).reduce((t,l) => t + parseFloat(l.qty||0), 0);
                            return tot < parseFloat(i.qty_ordered);          // <—— differenza
                        });
                        withoutShortfall = false;
                        if (hasShortfall) {                            
                            const goOn = confirm(
                                'Non tutto il materiale è stato ricevuto.\n' +
                                'Procedendo verrà generato un ordine di recupero con le quantità mancanti.\n\n' +
                                'Vuoi continuare?'
                            );
                            if (!goOn) {
                                withoutShortfall = confirm(
                                    'La registrazione sarà salvata senza generare un ordine di recupero?'
                                );
                                if (!withoutShortfall) return;       // utente annulla
                            }
                        }
                        const skipShortfall = hasShortfall && withoutShortfall;

                        /* build payload -------------------------------------- */
                        const payload = {
                            delivery_date : this.formData.delivery_date,
                            bill_number   : this.formData.bill_number,
                            skip_shortfall: skipShortfall           // <--- nuovo flag
                        };

                        /* 2‧ PATCH → /orders/supplier/{id}/registration --------------- */
                        try {
                            const url = `{{ url('/orders/supplier') }}/${this.formData.order_id}/registration`;

                            const resp = await fetch(url, {
                                method : 'PATCH',
                                headers: {
                                    'Accept'       : 'application/json',
                                    'Content-Type' : 'application/json',
                                    'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                                },
                                credentials : 'same-origin',
                                body : JSON.stringify(payload)
                            });

                            const j = await resp.json();

                            if (!resp.ok || !j.success) {
                                throw new Error(j.message || 'Errore HTTP ' + resp.status);
                            }

                            /* 3‧ aggiorna cache ordine localmente ---------------------- */
                            if (this.formData.order_id) {
                                Alpine.store('orderCache')[this.formData.order_id] = [...this.items];
                            }

                            /* 4‧ feedback finale -------------------------------------- */
                            const listShortfalls = (arr = []) => {
                                if (!Array.isArray(arr) || !arr.length) return '';
                                return arr.map(o => {
                                    const num = o.number ?? o.id ?? '?';
                                    const dt  = o.delivery_date ? ` (consegna ${o.delivery_date})` : '';
                                    const lt  = (o.lead_time_days ?? null) !== null ? ` — LT ${o.lead_time_days}g` : '';
                                    return `#${num}${dt}${lt}`;
                                }).join('\n');
                            };

                            const normalizeShortfalls = (resp) => {
                                // formato nuovo: array completo
                                if (Array.isArray(resp.follow_up_orders)) return resp.follow_up_orders;

                                // compat: vecchi campi singoli
                                if (resp.follow_up_order_id || resp.follow_up_number) {
                                    return [{
                                        id: resp.follow_up_order_id ?? null,
                                        number: resp.follow_up_number ?? null,
                                        delivery_date: resp.delivery_date ?? null,
                                        lead_time_days: resp.lead_time_days ?? null,
                                    }];
                                }
                                return [];
                            };

                            const shortfalls = normalizeShortfalls(j);

                            if (j.skipped) {
                                alert('Registrazione completata.\nNon è stato creato un ordine di recupero.');
                            } else if (j.shortfall_blocked === 'no_permission' && (j.shortfall_needed || hasShortfall)) {
                                alert(
                                    'Registrazione completata.\n' +
                                    'Sono state rilevate quantità mancanti ma non hai i permessi per creare ordini di recupero.\n' +
                                    'Comunica agli acquisti di generare un nuovo ordine con le quantità mancanti.'
                                );
                            } else if (shortfalls.length) {
                                alert(
                                    'Registrazione completata.\n' +
                                    'Generati ordini di recupero:\n' +
                                    listShortfalls(shortfalls)
                                );
                            } else if (hasShortfall) {
                                alert(
                                    'Registrazione completata.\n' +
                                    'Non è stato possibile generare nuovi ordini di recupero (già presenti o non necessari).'
                                );
                            } else {
                                alert('Registrazione completata.');
                            }

                            /* 5‧ reload pagina (solo esito positivo) ------------------- */
                            location.reload();

                        } catch (e) {
                            alert('Errore salvataggio registrazione: ' + e.message);
                        }
                    },

                });

                /* ───── STORE GLOBALE per la cache degli ordini ───── */
                Alpine.store('orderCache', {});

                /* ───── COMPONENTE per la tabella ───── */
                Alpine.data('entryCrud', () => ({
                    openId:   null,
                    extended: false,

                    /* proxy verso lo store, usando Alpine.store() */
                    openModal(data = null) { Alpine.store('entryModal').open(data); },
                    closeModal()          { Alpine.store('entryModal').close(); },
                }));

                /* ───── STORE GLOBALE per la sidebar righe ordine ───── */
                Alpine.store('orderSidebar', {
                    open    : false,
                    loading : false,
                    number  : '',
                    lines   : [],

                    openSidebar(orderId, orderNumber) {
                        this.open    = true;
                        this.loading = true;
                        this.number  = orderNumber;
                        this.lines   = [];

                        fetch(`/orders/supplier/${orderId}/lines`, {
                            headers: {Accept:'application/json'},
                            credentials:'same-origin'
                        })
                        .then(r => r.json())
                        .then(data => { this.lines = data; })
                        .catch(() => alert('Errore nel recupero righe ordine.'))
                        .finally(() => { this.loading = false; });
                    },
                    close() { this.open = false; }
                });

                /* ───── EVENTI PERSONALIZZATI ───── */
                document.addEventListener('open-row', e =>
                    Alpine.store('entryModal').loadRow(e.detail.itemId)
                );

                document.addEventListener('open-lines', e =>
                    Alpine.store('orderSidebar')
                        .openSidebar(e.detail.orderId, e.detail.orderNumber)
                );
            });
        </script>
    @endpush
</x-app-layout>
