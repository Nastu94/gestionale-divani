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
                                                                    $linked = $order->stockLevels
                                                                            ->firstWhere('component_id', $i->component_id);

                                                                    return [
                                                                        'id'            => $i->id,
                                                                        'code'          => $i->component->code,
                                                                        'name'          => $i->component->description,
                                                                        'qty_ordered'   => $i->quantity,
                                                                        'qty_received'  => $linked?->quantity ?? null,
                                                                        'lot_supplier'  => $linked?->supplier_lot_code ?? null,
                                                                        'internal_lot_code'  => $linked?->internal_lot_code ?? null,
                                                                        'unit'          => $i->component->unit_of_measure,
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
                                                        @click="$dispatch('open-view',{orderId:{{ $order->id }}})"
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

                    /* stato corrente della riga selezionata ----------------------------- */
                    currentRow: {
                        id           : null,   // id dell'OrderItem se proviene da ordine
                        component    : '',
                        component_code: '',
                        qty_ordered  : '',
                        qty_received : '',
                        unit         : '',
                        lot_supplier : '',
                        internal_lot_code : '',
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
                    close() { this.show = false; },

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
                    selectSupplier(o) {
                        this.selectedSupplier          = o;
                        this.formData.supplier_id      = o.id;
                        this.formData.supplier_name    = o.name;
                        this.supplierOptions           = [];
                    },
                    clearSupplier() {
                        this.selectedSupplier       = null;
                        this.formData.supplier_id   = null;
                        this.formData.supplier_name = '';
                        this.supplierSearch         = '';
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
                            id           : r.id,
                            component    : `${r.code} – ${r.name}`,
                            component_code: r.code,
                            qty_ordered  : r.qty_ordered,
                            qty_received : r.qty_received ?? r.qty_ordered,   // proposta = tutto ricevuto
                            unit         : r.unit,
                            lot_supplier : r.lot_supplier ?? '',
                            internal_lot_code : r.internal_lot_code ?? '',
                        };
                    },
                    resetRow() {
                        this.currentRow = {
                            id : null, component:'', component_code:'', qty_ordered:'',
                            qty_received:'', unit:'', lot_supplier:'', internal_lot_code:''
                        };
                    },
                    async saveRow() {
                        try {
                            const r = await fetch('{{ route('stock-movements-entry.store') }}', {
                                method : 'POST',
                                headers: {
                                    'Accept'       : 'application/json',
                                    'Content-Type' : 'application/json',
                                    'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                                },
                                credentials : 'same-origin',
                                body : JSON.stringify({
                                    order_id       : this.formData.order_id,          // null se create-mode
                                    component_code : this.currentRow.component_code,
                                    qty_received   : this.currentRow.qty_received,
                                    lot_supplier   : this.currentRow.lot_supplier,
                                    internal_lot_code   : this.currentRow.internal_lot_code,
                                })
                            });
                            if (!r.ok) throw new Error(await r.text());
                            const j = await r.json();

                            // 🔄 aggiorna la tabella senza ricaricare la pagina
                            const idx = this.items.findIndex(i => i.id === this.currentRow.id);
                            if (idx !== -1) {
                                this.items[idx] = {
                                    ...this.items[idx],
                                    qty_received : j.stock_level.quantity,
                                    lot_supplier : j.stock_level.supplier_lot_code,
                                    internal_lot_code : j.stock_level.internal_lot_code,
                                };
                            } else {
                                // riga extra in create-mode
                                this.items.push({
                                    id            : null,
                                    code          : this.currentRow.component_code,
                                    name          : this.currentRow.component,
                                    qty_ordered   : this.currentRow.qty_ordered,
                                    qty_received  : j.stock_level.quantity,
                                    lot_supplier  : j.stock_level.supplier_lot_code,
                                    internal_lot_code  : j.stock_level.internal_lot_code,
                                    unit          : this.currentRow.unit,
                                });
                            }

                            /* aggiorna la cache dell’ordine */
                            if (this.formData.order_id) {
                                Alpine.store('orderCache')[this.formData.order_id] = this.items;
                            }

                            this.resetRow();          // pulisci il form riga
                        } catch (e) {
                            alert('Errore nel salvataggio: ' + e.message);
                        }
                    },

                    /* Salva registrazione ordine fornitore */
                    async saveRegistration() {
                        const incomplete = this.items.some(i =>
                            !i.qty_received || !i.lot_supplier || !i.internal_lot_code
                        );

                        if (incomplete) {
                            alert('Registra tutte le righe (quantità + lotti) prima di salvare.');
                            return;
                        }

                        try {

                            const url = `{{ url('/orders/supplier') }}/${this.formData.order_id}/registration`;

                            const r = await fetch(url, {
                                method : 'PATCH',
                                headers: {
                                    'Accept'       : 'application/json',
                                    'Content-Type' : 'application/json',
                                    'X-CSRF-TOKEN' : document.querySelector('meta[name=\"csrf-token\"]').content
                                },
                                credentials : 'same-origin',
                                body : JSON.stringify({
                                    delivery_date : this.formData.delivery_date,
                                    bill_number   : this.formData.bill_number,
                                })
                            });
                            if (!r.ok) throw new Error(await r.text());
                            const j = await r.json();

                            /* aggiorna cache ordine */
                            if (this.formData.order_id) {
                                Alpine.store('orderCache')[this.formData.order_id] = [...this.items];
                            }
                            alert('Registrazione salvata con successo!');

                            location.reload();
                        } catch (e) {
                            alert('Errore salvataggio registrazione: ' + e.message);
                        }
                    },

                    /* Genera lotto interno */
                    async generateLot() {
                        try {
                            const r = await fetch('/lots/next', {
                                headers: {Accept:'application/json'},
                                credentials: 'same-origin'
                            });
                            if (!r.ok) throw new Error(r.status);
                            const j = await r.json();
                            this.currentRow.internal_lot_code = j.next;
                        } catch {
                            alert('Impossibile generare lotto.');
                        }
                    },

                });

                document.addEventListener('open-row', e =>
                    Alpine.store('entryModal').loadRow(e.detail.itemId)
                );

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
            });
        </script>
    @endpush
</x-app-layout>
