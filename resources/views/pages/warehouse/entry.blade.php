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
                                <th class="px-6 py-2 text-center">N. Ordine</th>
                                <th class="px-6 py-2 text-left">Fornitore</th>
                                {{-- Colonne nascoste in view compatta --}}
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left">Data ordine</th>
                                <th class="px-6 py-2 text-left">Data consegna</th>
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
                                    <td class="px-6 py-2 text-center">{{ $order->number ?? $order->id }}</td>
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
                                            {{-- Pulsante Registra --}}
                                            @can('stock.entry')
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
                'qty'      => $lot->quantity,
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
                                            @endcan

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
                            <th class="px-2 py-1 w-10">Unit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="l in $store.orderSidebar.lines" :key="l.id">
                            <tr>
                                <td class="px-2 py-1" x-text="l.code"></td>
                                <td class="px-2 py-1" x-text="l.desc"></td>
                                <td class="px-2 py-1 text-right" x-text="l.qty"></td>
                                <td class="px-2 py-1 text-right" x-text="l.qty_received"></td>
                                <td class="px-2 py-1" x-text="l.lot_supplier ?? '—'"></td>
                                <td class="px-2 py-1" x-text="l.internal_lot ?? '—'"></td>
                                <td class="px-2 py-1 uppercase" x-text="l.unit"></td>
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
                    open(data = null) {
                        this.isNew = !data;

                        // reset autocomplete
                        this.supplierSearch  = '';
                        this.supplierOptions = [];

                        // header
                        this.formData = data ?? {
                            order_id      : null,
                            supplier_id   : null,
                            supplier_name : '',
                            order_number  : '',
                            delivery_date : '',
                            bill_number   : '',
                        };

                        /* items: priorità alla cache */
                        const fromCache = data?.order_id
                            ? Alpine.store('orderCache')[data.order_id]
                            : null;

                        this.items = fromCache ?? (data?.items || []);

                        /* numero progressivo */
                        if (this.isNew) {
                            this.reserveOrderNumber();
                        } else {
                            this.formData.order_number_id = data.order_number_id ?? null;
                            this.formData.order_number    = data.order_number    ?? '';
                        }

                        /* fornitore pre‑compilato */
                        if (data?.supplier_id) {
                            this.selectedSupplier = {
                                id         : data.supplier_id,
                                name       : data.supplier_name,
                                email      : data.supplier_email,
                                vat_number : data.vat_number,
                                address    : data.address ?? { via:'', city:'', postal_code:'', country:'' },
                            };
                        } else {
                            this.selectedSupplier = null;
                        }

                        this.show = true;
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
                    },

                    /* seleziona componente dalla lista */
                    selectComponent(o) {
                        this.selectedComponent             = o;
                        this.currentRow.component_code     = o.code;
                        this.currentRow.component          = `${o.code} – ${o.name}`;
                        this.currentRow.unit               = o.unit;
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

                        this.currentRow = {
                            id             : r.id,
                            component      : `${r.code} – ${r.name}`,
                            component_code : r.code,
                            qty_ordered    : r.qty_ordered,
                            unit           : r.unit,
                            lot_supplier   : r.lots.length ? r.lots[0].supplier ?? '' : '',

                            // clona in profondità per evitare riferimenti condivisi
                            lots           : r.lots.length
                                            ? JSON.parse(JSON.stringify(r.lots))
                                            : [{ code:'', supplier:'', qty:'' }],
                        };
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
                        this.currentRow.lots.push({ code:'', supplier:this.currentRow.lot_supplier, qty:'' });
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
                            if (!resp.success) {
                                alert(resp.message || 'Errore sconosciuto');    // exit immediato
                                return;
                            }

                            /* 4‧ aggiorna la tabella reattiva -------------------------- */
                            if (idx !== -1) {
                                /* riga esiste già → pushiamo il nuovo lotto */
                                this.items[idx].lots.push({
                                    code     : resp.lot.internal_lot_code,
                                    supplier : resp.lot.supplier_lot_code,
                                    qty      : resp.lot.quantity,
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
                                    code     : resp.lot.internal_lot_code,
                                    supplier : resp.lot.supplier_lot_code,
                                    qty      : resp.lot.quantity,
                                });
                            }
                        }

                        /* 5‧ reset del form riga dopo ciclo completo ------------------- */
                        this.resetRow();
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

                        if (hasShortfall) {
                            const goOn = confirm(
                                'Non tutto il materiale è stato ricevuto.\n' +
                                'Procedendo verrà generato un ordine di recupero con le quantità mancanti.\n\n' +
                                'Vuoi continuare?'
                            );
                            if (!goOn) return;       // utente annulla
                        }

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
                                body : JSON.stringify({
                                    delivery_date : this.formData.delivery_date,
                                    bill_number   : this.formData.bill_number,
                                })
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
                            if (j.follow_up_order_id) {
                                alert('Registrazione OK.\nGenerato ordine di recupero #' + j.follow_up_number + '.');
                            } else {
                                alert('Registrazione salvata con successo!');
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
