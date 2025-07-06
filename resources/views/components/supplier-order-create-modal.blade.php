{{-- resources/views/components/supplier-order-create-modal.blade.php --}}
<div
    x-data="supplierOrderModal()"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
    x-init="window.addEventListener('open-supplier-order-modal', e => open(e.detail?.orderId));"
>
    {{-- BACKDROP --}}
    <div class="absolute inset-0 bg-black opacity-75" @click="close"></div>

    {{-- MODALE --}}
    <div
        class="relative bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200
               rounded-xl shadow-xl w-full max-w-5xl p-6 overflow-y-auto max-h-[99vh]"
    >
        {{-- HEADER --}}
        <div class="flex items-start justify-between mb-4">
            <h3 class="text-lg font-semibold tracking-wide">
                <span x-show="!editMode" x-cloak>Crea Ordine Fornitore</span>
                <span x-show="editMode"  x-cloak>Modifica Ordine #<span x-text="orderId"></span></span>
            </h3>
            <button @click="close" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times"></i></button>
        </div>

        {{-- FORM --}}
        <form @submit.prevent="editMode ? update() : save()" class="space-y-4">
            {{-- ========= DATI TESTATA ========= --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Colonna SX --}}
                <div class="space-y-4">
                    {{-- Numero ordine --}}
                    <div>
                        <label class="block text-sm font-medium">N. ordine</label>
                        <input type="text" x-model="order_number" class="w-full mt-1 input bg-gray-100" readonly>
                    </div>

                    {{-- Selezione fornitore --}}
                    <div class="relative">
                        <label x-show="!selectedSupplier" x-cloak class="block text-sm font-medium">Fornitore</label>

                        <input
                            type="text"
                            x-show="!selectedSupplier"
                            x-cloak
                            x-model="supplierSearch"
                            @input.debounce.500="searchSuppliers"
                            placeholder="Cerca fornitore..."
                            class="w-full mt-1 input"
                        >

                        {{-- Dropdown risultati – assoluto sopra gli altri --}}
                        <div
                            x-show="supplierOptions.length"
                            x-cloak
                            class="absolute z-50 w-full mt-1 bg-white border rounded shadow max-h-40 overflow-y-auto"
                        >
                            <template x-for="option in supplierOptions" :key="option.id">
                                <div class="px-2 py-1 hover:bg-gray-200 cursor-pointer"
                                     @click="selectSupplier(option)">
                                    <span x-text="option.name"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Riepilogo fornitore scelto --}}
                        <template x-if="selectedSupplier">
                            <div class="mt-2 p-2 border rounded bg-gray-50">
                                <p class="font-semibold" x-text="selectedSupplier.name"></p>
                                <p class="text-xs" x-text="selectedSupplier.email"></p>
                                <p class="text-xs" x-text="'P.IVA: ' + selectedSupplier.vat_number"></p>
                                <p class="text-xs"
                                   x-text="selectedSupplier.address.via + ', ' +
                                           selectedSupplier.address.city + ', ' +
                                           selectedSupplier.address.postal_code + ', ' +
                                           selectedSupplier.address.country">
                                </p>
                                <button type="button" @click="selectedSupplier=null"
                                        class="text-xs text-red-600 mt-1">Cambia</button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Colonna DX --}}
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Data consegna richiesta</label>
                        <input type="date" x-model="delivery_date" class="w-full mt-1 input" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Valore ordine (€)</label>
                        <input type="text" :value="formatCurrency(total)" class="w-full mt-1 input bg-gray-100" readonly>
                    </div>
                </div>
            </div>

            {{-- ========= RIGHE DELL'ORDINE ========= --}}
            <div class="border-t pt-4 mt-4">
                <h4 class="font-semibold mb-2">Righe dell'ordine</h4>

                <template x-if="!lines.length">
                    <p class="text-sm text-gray-500">Nessuna riga aggiunta.</p>
                </template>

                <table x-show="lines.length" x-cloak class="w-full text-sm border divide-y mt-2">
                    <thead class="text-left bg-gray-100">
                        <tr>
                            <th class="px-2 py-1">Codice</th>
                            <th class="px-2 py-1">Componente</th>
                            <th class="px-2 py-1 w-20 text-right">Q.tà</th>
                            <th class="px-2 py-1 w-20">Unità</th>
                            <th class="px-2 py-1 w-24 text-right">Prezzo</th>
                            <th class="px-2 py-1 w-24 text-right">Subtot.</th>
                            <th class="px-2 py-1 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line,idx) in lines" :key="idx">
                            <tr>
                                <td class="px-2 py-1" x-text="line.component.code"></td>
                                <td class="px-2 py-1" x-text="line.component.description"></td>
                                <td class="px-2 py-1 text-right" x-text="line.qty"></td>
                                <td class="px-2 py-1 uppercase" x-text="line.unit_of_measure"></td>
                                <td class="px-2 py-1 text-right" x-text="formatCurrency(line.last_cost)"></td>
                                <td class="px-2 py-1 text-right" x-text="formatCurrency(line.subtotal)"></td>
                                <td class="px-2 py-1 text-center space-x-2 flex justify-between">
                                    {{-- Modifica --}}
                                    <button type="button"
                                            @click="editLine(idx)"
                                            class="text-yellow-600 hover:text-yellow-800">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>

                                    {{-- Elimina --}}
                                    <button type="button"
                                            @click="removeLine(idx)"
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- ========= AGGIUNGI COMPONENTE ========= --}}
            <div class="border-t pt-4 mt-4"
                 x-bind:class="canAddLines ? '' : 'opacity-50 pointer-events-none select-none'">
                <h4 class="font-semibold mb-2">Aggiungi componente</h4>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Ricerca componente --}}
                    <div class="relative">
                        <label class="block text-sm font-medium">Ricerca componente</label>

                        <input type="text"
                               x-model="componentSearch"
                               @input.debounce.500="searchComponents"
                               placeholder="Cerca componente..."
                               class="w-full mt-1 input"
                               x-show="!selectedComponent"
                               x-cloak
                               :disabled="!canAddLines">

                        <div x-show="componentOptions.length"
                             x-cloak
                             class="absolute z-50 w-full mt-1 bg-white border rounded shadow max-h-40 overflow-y-auto">
                            <template x-for="option in componentOptions" :key="option.id">
                                <div class="px-2 py-1 hover:bg-gray-200 cursor-pointer"
                                     @click="selectComponent(option)">
                                    <span class="text-xs" x-text="option.code + ' — '"></span>
                                    <span class="text-xs" x-text="option.description"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Riepilogo componente scelto --}}
                        <template x-if="selectedComponent">
                            <div class="mt-2 p-2 border rounded bg-gray-50">
                                <p><strong x-text="selectedComponent.code"></strong> —
                                   <span x-text="selectedComponent.description"></span></p>
                                <p class="text-xs">
                                    Unità: <span x-text="unit_of_measure"></span>
                                    <br>
                                    <template x-if="selectedComponent.last_cost">
                                        <span x-text="'Prezzo listino: € ' + selectedComponent.last_cost"></span>
                                    </template>
                                </p>
                                <button type="button" @click="selectedComponent=null"
                                        class="text-xs text-red-600 mt-1">Cambia</button>
                            </div>
                        </template>
                    </div>

                    {{-- Quantità --}}
                    <div>
                        <label class="block text-sm font-medium">Quantità</label>
                        <input type="number" min="1" x-model.number="quantity"
                               class="w-full mt-1 input" :disabled="!canAddLines">
                        <p class="text-xs mt-1" x-text="unit_of_measure ? 'Unità: ' + unit_of_measure : ''"></p>
                    </div>

                    {{-- Prezzo + pulsante --}}
                    <div class="flex flex-col">
                        <label class="block text-sm font-medium">Prezzo (€)</label>
                        <input type="text" x-model="last_cost" class="w-full mt-1 input bg-gray-100" readonly>

                        <button type="button"
                                class="mt-3 inline-flex items-center justify-center
                                       px-3 py-1.5 bg-emerald-600 rounded-md text-xs
                                       font-semibold text-white uppercase hover:bg-emerald-500"
                                :disabled="!canAddLines || !selectedComponent || quantity <= 0"
                                @click="addLine">
                            <i class="fas fa-plus-square mr-1"></i> Aggiungi componente
                        </button>
                    </div>
                </div>
            </div>

            {{-- Salva --}}
            <div class="flex justify-end border-t pt-4 mt-4">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-purple-600
                            rounded-md text-sm font-semibold text-white uppercase
                            hover:bg-purple-500">
                    <i class="fas fa-save mr-2"></i>
                    <span x-text="editMode ? 'Modifica Ordine' : 'Salva Ordine'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ===================== SCRIPT ALPINE ===================== --}}
<script>
function supplierOrderModal() {
    return {
        /* ==== Stato base ==== */
        show      : false,
        editMode  : false,
        orderId   : null,

        /* ==== Dati ordine ==== */
        delivery_date : '',
        order_number_id : null, 
        order_number  : '—',

        /* ==== Fornitore ==== */
        supplierSearch  : '',
        supplierOptions : [],
        selectedSupplier: null,

        /* ==== Righe ordine ==== */
        lines            : [],
        componentSearch  : '',
        componentOptions : [],
        selectedComponent: null,
        unit_of_measure  : '',
        last_cost        : 0,
        quantity         : 1,

        /* ==== Getter computed ==== */
        get canAddLines() {
            return this.selectedSupplier && this.delivery_date;
        },

        /* ==== Apertura / chiusura ==== */
        open(id = null) {
            this.show     = true;
            this.editMode = !!id;
            this.orderId  = id;

            if (this.editMode) {
                this.fetchOrder(id);
            } else {
                this.resetForm();
                // progressivo sicuro (lo riserva già)
                fetch('/order-numbers/reserve', {
                    method : 'POST',
                    headers: {
                        'Accept'       : 'application/json',
                        'Content-Type' : 'application/json',
                        'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ type: 'supplier' })
                })
                .then(r => r.json())
                .then(j => {
                    this.order_number_id = j.id;
                    this.order_number    = j.number;
                })
                .catch(() => {
                    this.order_number_id = null;
                    this.order_number    = '—';
                });
            }
        },

        close() { 
            this.show = false; 
            this.resetForm(); 
            this.selectedSupplier = null;
            this.selectedComponent = null;
            this.supplierOptions = [];
            this.componentOptions = [];
            this.order_number_id = null;
        },

        resetForm() {
            this.delivery_date     = '';
            this.selectedSupplier  = null;
            this.supplierSearch    = '';
            this.lines             = [];
            this.selectedComponent = null;
            this.componentSearch   = '';
            this.unit_of_measure   = '';
            this.last_cost         = 0;
            this.quantity          = 1;
        },

        /* ==== Ricerca fornitori ==== */
        async searchSuppliers() {
            if (this.supplierSearch.trim().length < 2) { this.supplierOptions = []; return; }

            try {
                const r = await fetch(`/suppliers/search?q=${encodeURIComponent(this.supplierSearch.trim())}`, {
                    headers: { Accept: 'application/json' }, credentials: 'same-origin'
                });
                if (!r.ok) throw new Error(r.status);
                this.supplierOptions = await r.json();
            } catch { this.supplierOptions = []; }
        },
        selectSupplier(o) { this.selectedSupplier = o; this.supplierOptions = []; },

        /* ==== Ricerca componenti ==== */
        async searchComponents() {
            if (this.componentSearch.trim().length < 2) { this.componentOptions = []; return; }
            const supId = this.selectedSupplier ? this.selectedSupplier.id : '';

            try {
                const r = await fetch(
                    `/components/search?q=${encodeURIComponent(this.componentSearch.trim())}` +
                    (supId ? `&supplier_id=${supId}` : ''),
                    { headers: { Accept: 'application/json' }, credentials: 'same-origin' }
                );
                if (!r.ok) throw new Error(r.status);
                this.componentOptions = await r.json();
            } catch { this.componentOptions = []; }
        },
        selectComponent(c) {
            this.selectedComponent = c;
            this.unit_of_measure  = c.unit_of_measure;
            this.last_cost = c.last_cost ?? 0;
            this.componentOptions = [];
        },

        /* ==== Gestione righe ==== */
        addLine() {
            if (!this.selectedComponent || this.quantity <= 0) return;
            this.lines.push({
                component : this.selectedComponent,
                qty       : this.quantity,
                unit_of_measure : this.unit_of_measure,
                last_cost : this.last_cost,
                subtotal  : this.last_cost * this.quantity
            });
            // reset input
            this.selectedComponent = null; 
            this.componentSearch = '';
            this.unit_of_measure = ''; 
            this.last_cost = 0; 
            this.quantity = 1;
        },
        editLine(i) {
            // 1. estrai e rimuovi la riga
            const line = this.lines.splice(i, 1)[0];

            // 2. ripopola i campi input
            this.selectedComponent = line.component;
            this.unit_of_measure  = line.unit_of_measure;
            this.last_cost = line.last_cost;
            this.quantity = line.qty;

            // 3. mostra nuovamente l'input di ricerca vuoto
            this.componentSearch = '';
        },
        removeLine(i) { this.lines.splice(i, 1); },

        /* ==== Helpers ==== */
        formatCurrency(v) { return Intl.NumberFormat('it-IT', {minimumFractionDigits:2}).format(v); },
        get total()       { return this.lines.reduce((t,l) => t + l.subtotal, 0); },

        /* ==== Salva ordine ==== */
        async save() {
            if (!this.selectedSupplier || !this.delivery_date || !this.lines.length) {
                alert('Compila data consegna, fornitore e almeno una riga.');
                return;
            }

            const payload = {
                order_number_id : this.order_number_id,          // FK riservata
                supplier_id     : this.selectedSupplier.id,
                delivery_date   : this.delivery_date,
                lines           : this.lines.map(l => ({
                    component_id : l.component.id,
                    quantity     : l.qty,
                    last_cost    : l.last_cost
                }))
            };

            try {
                const resp = await fetch('/orders/supplier', {
                    method : 'POST',
                    headers: {
                        'Accept'       : 'application/json',
                        'Content-Type' : 'application/json',
                        'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials : 'same-origin',
                    body        : JSON.stringify(payload)
                });

                if (!resp.ok) throw new Error(await resp.text());

                // tutto ok → ricarica o chiudi modale
                this.close();
                window.location.reload();

            } catch (e) {
                console.error('Errore salvataggio', e);
                alert('Si è verificato un errore nel salvataggio.');
            }
        },

        /* ==== Fetch ordine esistente ==== */
        async fetchOrder(id) {
            try {
                const r = await fetch(`/orders/supplier/${id}/api`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                });

                if (!r.ok) {
                    const msg = (await r.json()).message ?? 'Errore';
                    throw new Error(`${r.status} – ${msg}`);
                }

                const o = await r.json();

                /* ▼ header */
                this.orderId         = o.id;            // utile per update()
                this.order_number_id = o.order_number_id;
                this.order_number    = o.order_number;
                this.selectedSupplier= o.supplier;
                this.delivery_date   = o.delivery_date;

                /* ▼ righe  (mappo nei nomi interni) */
                this.lines = o.lines.map(l => ({
                    id              : l.id,
                    component       : l.component,
                    qty             : l.qty,
                    unit_of_measure : l.unit_of_measure,    // ora esiste
                    last_cost       : Number(l.last_cost),      // coerente con addLine()
                    subtotal        : l.subtotal
                }));

                /* ▼ reset campi “nuova riga” */
                this.selectedComponent = null;
                this.componentSearch   = '';
                this.unit_of_measure   = '';
                this.last_cost         = 0;
                this.quantity          = 1;

            } catch (e) {
                console.error('Impossibile caricare ordine', e);
                alert(e.message);
                this.close();
            }
        },

        /* ==== Aggiorna ordine esistente ==== */
        async update() {
            if (!this.lines.length) { alert('Serve almeno una riga'); return }

            const payload = {
                delivery_date : this.delivery_date,
                lines         : this.lines.map(l => ({
                    id           : l.id ?? null,          // id riga se già esiste
                    component_id : l.component.id,
                    quantity     : l.qty,
                    last_cost    : l.last_cost
                }))
            };

            try {
                const r = await fetch(`/orders/supplier/${this.orderId}`, {
                    method : 'PUT',
                    headers: {
                        'Accept'       : 'application/json',
                        'Content-Type' : 'application/json',
                        'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                });
                if (!r.ok) throw new Error(await r.text());

                this.close();
                window.location.reload();
            } catch (e) {
                console.error('Errore update', e);
                alert('Errore durante la modifica.');
            }
        },

    };
}
</script>
