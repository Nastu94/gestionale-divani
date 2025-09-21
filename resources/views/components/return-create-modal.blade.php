{{-- resources/views/components/return-create-modal.blade.php
    =========================================================================
    |  Modale Crea / Modifica Reso Cliente
    |  – PHP 8.4 / Laravel 12 – completamente commentato
    |  – Ricerca cliente/prodotto; variabili prodotto (fabric/color) dinamiche
    |  – Abbinamento a Ordine Cliente (ricerca + coerenza cliente)
    |  – Nessuna scelta magazzino: sarà fatta lato controller in store/update
    |  – Fix Alpine/Livewire: chiavi sicure __key e wire:ignore su dropdown
    |  – Sezione riga su 3 righe:
    |       1) Prodotto, Quantità
    |       2) Tessuto, Colore, Condizione, Motivo
    |       3) Note riga, Checkbox "Rientra"
    =========================================================================
--}}

<div
    x-data="returnModal()"
    x-on:open-return-create.window="openCreate()"
    x-on:open-return-edit.window="openEdit($event.detail?.id)"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-[9999] flex items-center justify-center"
    wire:ignore   {{-- Evita che Livewire modifichi il DOM gestito da Alpine --}}
>
    {{-- BACKDROP --}}
    <div class="absolute inset-0 bg-black/70"></div>

    {{-- DIALOG --}}
    <div class="relative bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100
                rounded-xl shadow-xl w-full max-w-5xl p-6 overflow-y-auto max-h-[98vh]">

        {{-- HEADER --}}
        <div class="flex items-start justify-between mb-4">
            <h3 class="text-lg font-semibold tracking-wide">
                <span x-show="mode==='create'" x-cloak>Nuovo Reso</span>
                <span x-show="mode==='edit'"   x-cloak>Modifica Reso <span class="text-gray-500">#</span><span x-text="form.number"></span></span>
            </h3>
            <button @click="close()" class="text-gray-400 hover:text-gray-200"><i class="fas fa-times"></i></button>
        </div>

        {{-- ================= FORM ================= --}}
        <form :action="action" method="POST" @submit.prevent="submit($event)" class="space-y-5">
            @csrf
            <template x-if="mode==='edit'">
                <input type="hidden" name="_method" value="PUT">
            </template>

            {{-- Hidden: righe serializzate per il backend (JSON) --}}
            <input type="hidden" name="lines_json" :value="JSON.stringify(lines)">

            {{-- ===== TESTATA (3 righe) ===== --}}
            <div class="space-y-4">
                {{-- RIGA 1: N. Reso + Data Reso --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- N. Reso (autogenerato) --}}
                    <div>
                        <label class="block text-sm font-medium">N. Reso</label>
                        <input type="text"
                            name="number"
                            x-model="form.number"
                            readonly
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">
                    </div>

                    {{-- Data Reso --}}
                    <div>
                        <label class="block text-sm font-medium">Data Reso</label>
                        <input type="date" name="return_date" x-model="form.return_date" required
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">
                    </div>
                </div>

                {{-- RIGA 2: Cliente + Ordine --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Cliente: ricerca con dropdown --}}
                    <div class="relative">
                        <label class="block text-sm font-medium">Cliente</label>

                        {{-- Input ricerca (se non selezionato) --}}
                        <input type="text"
                            x-show="!selectedCustomer"
                            x-cloak
                            x-model="customerSearch"
                            @input.debounce.400ms="searchCustomers"
                            placeholder="Cerca cliente…"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">

                        {{-- Dropdown risultati (isolato da Livewire) --}}
                        <div x-show="customerOptions.length"
                            x-cloak
                            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border rounded shadow max-h-48 overflow-y-auto"
                            wire:ignore>
                            <template x-for="(opt, idx) in customerOptions" :key="opt.__key ?? ('c-'+(opt.id ?? idx))">
                                <div class="px-2 py-1 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer"
                                    @click="selectCustomer(opt)">
                                    <span class="text-xs" x-text="(opt.company || opt.name) + (opt.shipping_address ? ' — ' + opt.shipping_address : '')"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Riepilogo cliente scelto --}}
                        <template x-if="selectedCustomer">
                            <div class="mt-2 p-2 border rounded bg-gray-50 dark:bg-gray-800">
                                <p class="text-sm font-semibold" x-text="selectedCustomer.company || selectedCustomer.name"></p>
                                <template x-if="selectedCustomer.email">
                                    <p class="text-xs" x-text="selectedCustomer.email"></p>
                                </template>
                                <template x-if="selectedCustomer.shipping_address">
                                    <p class="text-xs" x-text="selectedCustomer.shipping_address"></p>
                                </template>
                                <button x-show="mode=='create'" type="button" @click="selectedCustomer=null" class="text-xs text-red-600 mt-1">Cambia</button>
                            </div>
                        </template>

                        {{-- Campo effettivo per submit --}}
                        <input type="hidden" name="customer_id" :value="selectedCustomer?.id || ''">
                    </div>

                    {{-- Ordine Cliente: ricerca con dropdown + allineamento cliente --}}
                    <div class="relative">
                        <label class="block text-sm font-medium">Abbina a Ordine</label>

                        {{-- Input ricerca (se non selezionato) --}}
                        <input type="text"
                            x-show="!selectedOrder"
                            x-cloak
                            x-model="orderSearch"
                            @input.debounce.400ms="searchOrders"
                            placeholder="Cerca per numero o cliente…"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">

                        {{-- Dropdown risultati (isolato da Livewire) --}}
                        <div x-show="orderOptions.length"
                            x-cloak
                            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border rounded shadow max-h-56 overflow-y-auto"
                            wire:ignore>
                            <template x-for="(opt, idx) in orderOptions" :key="opt.__key ?? ('o-'+(opt.id ?? idx))">
                                <div class="px-2 py-1 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer"
                                    @click="selectOrder(opt)">
                                    <div class="leading-tight">
                                        <span class="ml-1 text-sm font-semibold" x-text="(opt.customer?.company || '—')"></span>
                                        <span class="text-sm font-semibold"
                                            x-text="`Ordine ${ (opt?.number != null && String(opt.number).trim() !== '' ) ? opt.number : ('#' + opt.id) }`">
                                        </span>
                                        <span class="text-sm block opacity-70"
                                            x-text="'Consegna: ' + (opt.delivery_date || '—') + ' · Ordine: ' + (opt.ordered_at || '—')"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Riepilogo ordine scelto --}}
                        <template x-if="selectedOrder">
                            <div class="mt-2 p-2 border rounded bg-gray-50 dark:bg-gray-800">
                                <p class="text-sm font-semibold">                                    
                                    <span
                                        x-text="`Ordine ${ (selectedOrder?.number != null) ? selectedOrder.number : ('#' + selectedOrder?.id) }`">
                                    </span>
                                    <span class="text-xs ml-1"
                                            x-show="selectedOrder?.delivery_date"
                                            x-text="'— consegna ' + selectedOrder.delivery_date"></span>
                                </p>
                                <p class="text-xs" x-text="selectedOrder.customer ? (selectedOrder.customer.company || selectedOrder.customer.name) : '—'"></p>
                                <button x-show="mode=='create'" type="button" @click="clearOrder()" class="text-xs text-red-600 mt-1">Rimuovi</button>
                            </div>
                        </template>

                        {{-- Campo effettivo per submit --}}
                        <input type="hidden" name="order_id" :value="selectedOrder?.id || ''">
                    </div>
                </div>

                {{-- RIGA 3: Note (full width) --}}
                <div>
                    <label class="block text-sm font-medium">Note</label>
                    <textarea name="notes" x-model="form.notes" rows="2"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm"></textarea>
                </div>
            </div>

            {{-- ===== TABELLA RIGHE INSERITE ===== --}}
            <div>
                <table class="table-auto w-full text-xs mb-3">
                    <thead class="bg-gray-200 dark:bg-gray-800">
                        <tr>
                            <th class="px-2 py-1 text-left">Prodotto</th>
                            <th class="px-2 py-1 text-left">Tessuto</th>
                            <th class="px-2 py-1 text-left">Colore</th>
                            <th class="px-2 py-1 w-16 text-right">Q.tà</th>
                            <th class="px-2 py-1 w-20 text-left">Rientra</th>
                            <th class="px-2 py-1 w-24 text-left">Condizione</th>
                            <th class="px-2 py-1 w-24 text-left">Motivo</th>
                            <th class="px-2 py-1 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="lines.length===0">
                            <tr><td colspan="8" class="px-2 py-2 text-center text-gray-500">Nessuna riga inserita.</td></tr>
                        </template>

                        <template x-for="(l, idx) in lines" :key="'line-'+idx">
                            <tr class="border-b">
                                <td class="px-2 py-1" x-text="l.product.sku + ' — ' + l.product.name"></td>
                                <td class="px-2 py-1" x-text="l.fabric_name || '—'"></td>
                                <td class="px-2 py-1" x-text="l.color_name  || '—'"></td>
                                <td class="px-2 py-1 text-right" x-text="l.quantity"></td>
                                <td class="px-2 py-1">
                                    <span class="inline-flex px-2 py-0.5 rounded-full"
                                          :class="l.restock ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300'">
                                        <span x-text="l.restock ? 'Sì' : 'No'"></span>
                                    </span>
                                </td>
                                <td class="px-2 py-1" x-text="l.condition"></td>
                                <td class="px-2 py-1" x-text="l.reason"></td>
                                <td class="px-2 py-1 text-center flex items-center justify-center">
                                    <button type="button" @click="editLine(idx)" class="text-indigo-600"><i class="fas fa-pen"></i></button>
                                    <button type="button" @click="removeLine(idx)" class="text-red-600 ml-2"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- ===== SEZIONE AGGIUNGI/MODIFICA RIGA (3 righe) ===== --}}
            <div class="border-t pt-3">
                <h4 class="font-semibold mb-2 text-sm">Aggiungi riga reso</h4>

                {{-- RIGA 1: Prodotto, Quantità --}}
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    {{-- Prodotto (ricerca) --}}
                    <div class="md:col-span-8 relative">
                        <label class="block text-xs font-medium">Prodotto</label>

                        <input type="text"
                               x-model="productSearch"
                               @input.debounce.400ms="searchProducts"
                               placeholder="Cerca prodotto…"
                               x-show="!selectedProduct"
                               x-cloak
                               class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">

                        {{-- Dropdown risultati (isolato da Livewire) --}}
                        <div x-show="productOptions.length"
                             x-cloak
                             class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border rounded shadow max-h-48 overflow-y-auto"
                             wire:ignore>
                            <template x-for="(opt, idx) in productOptions" :key="opt.__key ?? ('p-'+(opt.id ?? idx))">
                                <div class="px-2 py-1 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer"
                                     @click="selectProduct(opt)">
                                    <span class="text-xs" x-text="opt.sku + ' — ' + opt.name"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Riepilogo prodotto scelto --}}
                        <template x-if="selectedProduct">
                            <div class="mt-2 p-2 border rounded bg-gray-50 dark:bg-gray-800">
                                <p><strong x-text="selectedProduct.sku"></strong> — <span x-text="selectedProduct.name"></span></p>
                                <button type="button"
                                        @click="selectedProduct=null; fabricOptions=[]; colorOptions=[]; fabric_id=''; color_id='';"
                                        class="text-xs text-red-600 mt-1">Cambia</button>
                            </div>
                        </template>
                    </div>

                    {{-- Quantità --}}
                    <div class="md:col-span-4">
                        <label class="block text-xs font-medium">Q.tà</label>
                        <input type="number" min="1" step="1" x-model.number="quantity"
                               class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">
                    </div>
                </div>

                {{-- RIGA 2: Tessuto, Colore, Condizione, Motivo --}}
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mt-3">
                    {{-- Tessuto (dipende dal prodotto) --}}
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium">Tessuto</label>
                        <select x-model.number="fabric_id"
                                :disabled="!selectedProduct || fabricOptions.length===0"
                                class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">
                            <option value="">—</option>
                            <template x-for="f in fabricOptions" :key="'f-'+f.id">
                                <option :value="f.id" x-text="f.name"></option>
                            </template>
                        </select>
                        <p class="text-[11px] text-amber-600 mt-1" x-show="selectedProduct && fabricOptions.length===0" x-cloak>
                            Nessun tessuto disponibile per questo prodotto.
                        </p>
                    </div>

                    {{-- Colore (dipende dal prodotto) --}}
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium">Colore</label>
                        <select x-model.number="color_id"
                                :disabled="!selectedProduct || colorOptions.length===0"
                                class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">
                            <option value="">—</option>
                            <template x-for="c in colorOptions" :key="'c-'+c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                        <p class="text-[11px] text-amber-600 mt-1" x-show="selectedProduct && colorOptions.length===0" x-cloak>
                            Nessun colore disponibile per questo prodotto.
                        </p>
                    </div>

                    {{-- Condizione --}}
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium">Condizione</label>
                        <select x-model="condition"
                                class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="REFURB">REFURB</option>
                            <option value="SCRAP">SCRAP</option>
                        </select>
                    </div>

                    {{-- Motivo --}}
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium">Motivo</label>
                        <select x-model="reason"
                                class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">
                            <option value="difettoso">difettoso</option>
                            <option value="errato">errato</option>
                            <option value="invenduto">invenduto</option>
                            <option value="altro">altro</option>
                        </select>
                    </div>
                </div>

                {{-- RIGA 3: Note riga, Checkbox Rientra --}}
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 mt-3">
                    {{-- Note riga --}}
                    <div class="md:col-span-10">
                        <label class="block text-xs font-medium">Note riga</label>
                        <input type="text" x-model="rowNotes"
                               class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-800 text-sm">
                    </div>

                    {{-- Checkbox Rientra a magazzino --}}
                    <div class="md:col-span-2 flex items-end">
                        <label class="inline-flex items-center text-xs mt-6">
                            <input type="checkbox" x-model="restock"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 mr-2">
                            Rientra
                        </label>
                    </div>
                </div>

                {{-- Pulsanti riga --}}
                <div class="mt-3 flex items-center justify-end gap-2">
                    <button type="button" @click="resetRow()" class="px-3 py-1.5 rounded border text-xs">Reset</button>

                    <template x-if="editingIndex===null">
                        <button type="button" @click="addLine()" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700">
                            Aggiungi riga
                        </button>
                    </template>

                    <template x-if="editingIndex!==null">
                        <button type="button" @click="confirmEditLine()" class="px-3 py-1.5 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                            Applica modifiche
                        </button>
                    </template>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="flex justify-end pt-2 border-t">
                <button type="button" @click="close()" class="px-3 py-1.5 rounded border text-xs mr-2">Annulla</button>
                @can('orders.customer.returns_manage')
                    <button type="submit" class="px-3 py-1.5 bg-purple-600 text-white rounded text-xs hover:bg-purple-500">
                        Salva
                    </button>
                @endcan
            </div>
        </form>
    </div>
</div>

{{-- ===================== SCRIPT ALPINE ===================== --}}
<script>
/**
 * Cache globale delle variabili (fabric/color) per opzione selettiva.
 * Endpoint atteso: GET /products/variables/options  → { fabrics:[{id,name}], colors:[{id,name}] }
 */
if (!window.GD_VARIABLE_OPTIONS) {
    fetch('/products/variables/options', { headers:{ Accept:'application/json' }})
        .then(r => r.json())
        .then(data => { window.GD_VARIABLE_OPTIONS = data || { fabrics:[], colors:[] }; })
        .catch(() =>   { window.GD_VARIABLE_OPTIONS = { fabrics:[], colors:[] }; });
}
window.GD_findName = (arr, id) => (arr || []).find(x => Number(x.id) === Number(id))?.name ?? null;

/**
 * Comportamento del modale “Resi”:
 *  - Ricerca cliente/prodotto/ordine con dropdown
 *  - Caricamento whitelist tessuti/colori da /products/{id}/variables
 *  - Niente scelta magazzino: gestita dal controller per righe restock=true
 */
function returnModal() {
    return {
        /* Stato generale */
        show: false,
        mode: 'create',              // 'create' | 'edit'
        id: null,
        action: '{{ route('returns.store') }}',

        /* Testata */
        form: {
            number: '—',
            return_date: new Date().toISOString().slice(0,10),
            notes: '',
        },
        _reserving: false,

        /* Cliente */
        selectedCustomer: null,
        customerSearch: '',
        customerOptions: [],

        /* Ordine Cliente */
        selectedOrder: null,
        orderSearch: '',
        orderOptions: [],

        /* Righe inserite (per lines_json) */
        lines: [],

        /* Stato riga corrente */
        selectedProduct: null,
        productSearch: '',
        productOptions: [],
        fabricOptions: [],
        colorOptions: [],
        fabric_id: '',
        color_id: '',
        quantity: 1,
        restock: true,
        condition: 'A',
        reason: 'altro',
        rowNotes: '',
        editingIndex: null,

        /* Apertura/chiusura */
        openCreate() {
            this.mode = 'create'; this.id = null;
            this.action = '{{ route('returns.store') }}';
            this.resetHeader(); this.resetRow(); this.lines = [];
            this.reserveReturnNumber();
            this.show = true;
        },
        async openEdit(id) {
            this.mode = 'edit'; this.id = id;
            this.action = '{{ route('returns.update', ['return' => '__ID__']) }}'.replace('__ID__', id);
            this.resetHeader(); this.resetRow(); this.lines = []; this.show = true;

            // Carica JSON del reso esistente
            try {
                const r = await fetch('{{ route('returns.show', ['return' => '__ID__']) }}'.replace('__ID__', id), {
                    headers:{ Accept:'application/json' }
                });
                if (!r.ok) throw new Error('HTTP '+r.status);
                const js = await r.json();

                this.form.number = js.number ?? '—';
                this.form.return_date = js.return_date ?? new Date().toISOString().slice(0,10);
                this.form.notes = js.notes ?? '';

                // Cliente
                this.selectedCustomer = js.customer ?? null;
                this.customerSearch   = (this.selectedCustomer?.company || this.selectedCustomer?.name || '') ?? '';

                // Ordine associato (se presente)
                if (js.order) {
                    this.selectedOrder = {
                        id: js.order.id,
                        number: js.order.number,
                        ordered_at: js.order.ordered_at ?? null,
                        delivery_date: js.order.delivery_date ?? null,
                        customer: js.order.customer ?? this.selectedCustomer ?? null,
                    };
                    // Assicura coerenza cliente
                    if (!this.selectedCustomer && this.selectedOrder.customer) {
                        this.selectedCustomer = this.selectedOrder.customer;
                    }
                }

                // Righe
                this.lines = (js.lines || []).map(l => ({
                    product     : { id:l.product_id, sku:l.product?.sku ?? l.sku, name:l.product?.name ?? l.name },
                    quantity    : Number(l.quantity) || 1,
                    fabric_id   : l.fabric_id || null,
                    color_id    : l.color_id  || null,
                    fabric_name : window.GD_findName(window.GD_VARIABLE_OPTIONS.fabrics, l.fabric_id),
                    color_name  : window.GD_findName(window.GD_VARIABLE_OPTIONS.colors,  l.color_id),
                    restock     : !!l.restock,
                    condition   : l.condition ?? 'A',
                    reason      : l.reason ?? 'altro',
                    notes       : l.notes ?? '',
                }));
            } catch (e) {
                console.error('load return failed', e);
                alert('Impossibile caricare il reso.');
                this.close();
            }
        },
        close(){ this.show = false; },

        /* Reset */
        resetHeader(){
            this.form = { number:'', return_date:new Date().toISOString().slice(0,10), notes:'' };
            this.selectedCustomer = null; this.customerSearch=''; this.customerOptions=[];
            this.selectedOrder = null; this.orderSearch=''; this.orderOptions=[];
        },
        resetRow(){
            this.selectedProduct=null; this.productSearch=''; this.productOptions=[];
            this.fabricOptions=[]; this.colorOptions=[]; this.fabric_id=''; this.color_id='';
            this.quantity=1; this.restock=true; this.condition='A'; this.reason='altro'; this.rowNotes='';
            this.editingIndex=null;
        },

        /* Riserva un numero reso (solo in create) */
        reserveReturnNumber() {
            if (this._reserving || this.form.number) return; // evita doppie chiamate
            this._reserving = true;

            fetch('/order-numbers/reserve', {
                method: 'POST',
                headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                credentials: 'same-origin',
                body: JSON.stringify({ type: 'return' })
            })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(j => { this.form.number = j.number; }) // usiamo il progressivo ritornato
            .catch(() => { this.form.number = ''; })     // fallback: campo vuoto
            .finally(() => { this._reserving = false; });
        },

        /* Ricerca CLIENTI con chiavi sicure (__key) */
        async searchCustomers () {
            if (this.customerSearch.trim().length < 2) {
                this.customerOptions = []; return;
            }
            const qs = new URLSearchParams({
                q: this.customerSearch.trim(),
                include_occasional: '1',   // <— SOLO qui nei Resi
                limit: '20',
            });

            try {
                const r = await fetch(`/customers/search?${qs.toString()}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                });
                if (!r.ok) throw new Error(r.status);

                const list = await r.json();
                this.customerOptions = Array.isArray(list) ? list : [];
            } catch {
                this.customerOptions = [];
            }
        },

        selectCustomer(opt){
            this.selectedCustomer = opt; this.customerOptions = [];

            // Se è già selezionato un ordine con cliente differente → chiedi conferma e riallinea
            if (this.selectedOrder && this.selectedOrder.customer && Number(this.selectedOrder.customer.id) !== Number(opt.id)) {
                const ok = confirm('Il cliente selezionato è diverso da quello dell’ordine abbinato. Vuoi rimuovere l’ordine selezionato?');
                if (ok) this.clearOrder();
            }
        },

        /* Ricerca ORDINI con chiavi sicure (__key) */
        async searchOrders() {
            if (!this.orderSearch || this.orderSearch.trim().length < 2) {
                this.orderOptions = []; return;
            }

            const qs = new URLSearchParams({
                q: this.orderSearch.trim(),
                limit: 20,
            });

            // SE ho già un cliente selezionato → filtra lato BE
            if (this.selectedCustomer && this.selectedCustomer.id) {
                if (this.selectedCustomer.source === 'occasional') {
                    qs.set('occasional_customer_id', String(this.selectedCustomer.id));
                } else {
                    qs.set('customer_id', String(this.selectedCustomer.id));
                }
            }

            try {
                const r = await fetch(`/orders/customer/search?${qs.toString()}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (!r.ok) throw new Error(String(r.status));
                this.orderOptions = await r.json();
            } catch (e) {
                console.error('orders search failed', e);
                this.orderOptions = [];
            }
        },
        // Selezione ordine dall'autocomplete (create/edit)
        selectOrder(opt) {
            // ── normalizza il campo number: intero o null (mai stringa vuota)
            const normalizedNumber =
                (opt && opt.number != null && String(opt.number).trim() !== '')
                    ? Number(opt.number)
                    : null;

            // proposta ordine
            const chosen = {
                id: opt.id,
                number: normalizedNumber,                 // <— ora è int o null
                delivery_date: opt.delivery_date ?? null,
                customer: opt.customer ?? null,
                shipping_address: opt.shipping_address ?? null,
            };

            // coerenza con cliente corrente
            if (!this.selectedCustomer && chosen.customer) {
                this.selectedCustomer = chosen.customer;
            } else if (
                this.selectedCustomer &&
                chosen.customer &&
                Number(this.selectedCustomer.id) !== Number(chosen.customer.id)
            ) {
                const ok = confirm(
                    'Il cliente dell’ordine selezionato è diverso dal cliente attuale. ' +
                    'Vuoi sostituire il cliente con quello dell’ordine?'
                );
                if (!ok) {
                    // rollback se non vuoi cambiare cliente
                    this.selectedOrder = null;
                    this.orderOptions  = [];
                    this.form.order_id = null;
                    return;
                }
                this.selectedCustomer = chosen.customer;
            }

            // conferma selezione
            this.selectedOrder = chosen;
            this.form.order_id = chosen.id;
            this.orderOptions  = [];
        },
        clearOrder(){
            this.selectedOrder = null;
            this.orderSearch   = '';
        },

        /* Ricerca PRODOTTI con chiavi sicure (__key) */
        async searchProducts(){
            const q = (this.productSearch || '').trim();
            if (q.length < 2) { this.productOptions = []; return; }
            try {
                const r = await fetch(`/products/search?` + new URLSearchParams({ q }), {
                    headers:{ Accept:'application/json' }
                });
                const data = r.ok ? await r.json() : [];
                this.productOptions = (Array.isArray(data) ? data : []).map((o, i) => ({
                    ...o,
                    __key: `p-${ (o.id!=null?o.id:(o.uuid||o.sku||'row')) }-${i}`
                }));
            } catch {
                this.productOptions = [];
            }
        },
        async selectProduct(opt){
            this.selectedProduct = opt; this.productOptions = [];
            await this.loadProductWhitelist(opt.id);
        },

        /* Carica whitelist variabili per prodotto selezionato */
        async loadProductWhitelist(productId, preF=null, preC=null){
            this.fabricOptions=[]; this.colorOptions=[]; this.fabric_id=''; this.color_id='';
            if (!productId) return;
            try {
                const r  = await fetch(`/products/${productId}/variables`, { headers:{Accept:'application/json'} });
                if (!r.ok) throw new Error('HTTP '+r.status);
                const js = await r.json();

                const allF = (window.GD_VARIABLE_OPTIONS?.fabrics) || [];
                const allC = (window.GD_VARIABLE_OPTIONS?.colors)  || [];

                const allowedF = new Set((js.fabric_ids || []).map(n => Number(n)));
                const allowedC = new Set((js.color_ids  || []).map(n => Number(n)));

                this.fabricOptions = allF.filter(f => allowedF.has(Number(f.id)));
                this.colorOptions  = allC.filter(c => allowedC.has(Number(c.id)));

                const defF = Number(js.default_fabric_id || 0);
                const defC = Number(js.default_color_id  || 0);
                const pick = (preferred, def, allowed, opts) => {
                    const P = Number(preferred||0), D = Number(def||0);
                    if (P && allowed.has(P)) return P;
                    if (D && allowed.has(D)) return D;
                    return Number(opts[0]?.id || 0) || '';
                };

                this.fabric_id = pick(preF, defF, allowedF, this.fabricOptions);
                this.color_id  = pick(preC, defC, allowedC, this.colorOptions);
            } catch(e) {
                console.error('variables failed', e);
                this.fabricOptions=[]; this.colorOptions=[]; this.fabric_id=''; this.color_id='';
            }
        },

        /* Gestione RIGHE */
        addLine(){
            if (!this.selectedProduct) { alert('Seleziona un prodotto'); return; }
            if (!this.selectedCustomer) { alert('Seleziona un cliente'); return; }
            if (!this.quantity || this.quantity < 1) { alert('Quantità non valida'); return; }

            this.lines.push({
                product     : this.selectedProduct,
                quantity    : Number(this.quantity),
                fabric_id   : this.fabric_id || null,
                color_id    : this.color_id  || null,
                fabric_name : window.GD_findName(window.GD_VARIABLE_OPTIONS.fabrics, this.fabric_id),
                color_name  : window.GD_findName(window.GD_VARIABLE_OPTIONS.colors,  this.color_id),
                restock     : !!this.restock,
                condition   : this.condition,
                reason      : this.reason,
                notes       : (this.rowNotes || '').trim(),
            });
            this.resetRow();
        },
        editLine(i){
            const l = this.lines[i];
            this.editingIndex = i;
            this.selectedProduct = l.product;
            this.quantity  = l.quantity;
            this.restock   = !!l.restock;
            this.condition = l.condition;
            this.reason    = l.reason;
            this.rowNotes  = l.notes || '';
            this.loadProductWhitelist(l.product.id, l.fabric_id ?? null, l.color_id ?? null);
        },
        confirmEditLine(){
            if (this.editingIndex === null) return;
            if (!this.selectedProduct) { alert('Seleziona un prodotto'); return; }
            if (!this.quantity || this.quantity < 1) { alert('Quantità non valida'); return; }

            this.lines.splice(this.editingIndex, 1, {
                product     : this.selectedProduct,
                quantity    : Number(this.quantity),
                fabric_id   : this.fabric_id || null,
                color_id    : this.color_id  || null,
                fabric_name : window.GD_findName(window.GD_VARIABLE_OPTIONS.fabrics, this.fabric_id),
                color_name  : window.GD_findName(window.GD_VARIABLE_OPTIONS.colors,  this.color_id),
                restock     : !!this.restock,
                condition   : this.condition,
                reason      : this.reason,
                notes       : (this.rowNotes || '').trim(),
            });
            this.resetRow();
        },
        removeLine(i){
            if (!confirm('Eliminare la riga selezionata?')) return;
            this.lines.splice(i,1);
        },

        /* Submit verso BE (store/update) */
        async submit(ev){
            if (!this.selectedCustomer) { alert('Seleziona un cliente'); return; }
            if (!this.form.return_date) { alert('Inserisci la data reso'); return; }
            // se c'è un ordine selezionato ma cliente diverso, bloccati (paranoia server-side)
            if (this.selectedOrder?.customer?.id && this.selectedCustomer?.id
                && Number(this.selectedOrder.customer.id) !== Number(this.selectedCustomer.id)) {
                alert('Il cliente selezionato non coincide con quello dell’ordine. Allinea i dati prima di salvare.');
                return;
            }
            ev.target.submit();   // nessun magazzino qui: gestito dal controller quando restock=true
        },
    }
}
</script>
