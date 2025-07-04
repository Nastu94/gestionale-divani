{{-- resources/views/components/supplier-order-create-modal.blade.php --}}
{{-- ─────────────────────────────────────────────────────────────────── --}}
{{--  Modale per creare/modificare un ordine fornitore                   --}}

<div
    x-data="supplierOrderModal()"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
    x-init="
        window.addEventListener('open-supplier-order-modal', e => open(e.detail?.orderId));
    "
>
    {{-- BACKDROP --}}
    <div class="absolute inset-0 bg-black opacity-75" @click="close"></div>

    {{-- MODALE --}}
    <div
        class="relative bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200
               rounded-xl shadow-xl w-full max-w-5xl p-6 overflow-y-auto max-h-[90vh]"
    >
        {{-- ───────── HEADER ───────── --}}
        <div class="flex items-start justify-between mb-4">
            <h3 class="text-lg font-semibold tracking-wide">
                <!-- Nuovo ordine -->
                <span x-show="!editMode" x-cloak>Crea Ordine Fornitore</span>

                <!-- Modifica -->
                <span x-show="editMode"  x-cloak>
                    Modifica Ordine #<span x-text="orderId"></span>
                </span>
            </h3>
            <button @click="close" class="text-gray-500 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- ───────── FORM PRINCIPALE ───────── --}}
        <form @submit.prevent="save" class="space-y-4">
            {{-- ▲ Top : date + supplier + valori ordine --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Colonna SX --}}
                <div class="space-y-4">
                    {{-- Data consegna richiesta --}}
                    <div>
                        <label class="block text-sm font-medium">Data consegna richiesta</label>
                        <input type="date" x-model="delivery_date" class="w-full mt-1 input" required>
                    </div>

                    {{-- Numero ordine (readonly) --}}
                    <div>
                        <label class="block text-sm font-medium">N. ordine (automatico)</label>
                        <input type="text" x-model="order_number"
                               class="w-full mt-1 input bg-gray-100" readonly>
                    </div>
                </div>

                {{-- Colonna DX --}}
                <div class="space-y-4">
                    {{-- Ricerca / selezione fornitore --}}
                    <div>
                        <label x-show="!selectedSupplier" x-cloak class="block text-sm font-medium">Fornitore</label>
                        {{-- search box (disattivato se fornitore già scelto) --}}
                        <input
                            type="text"
                            x-show="!selectedSupplier"
                            x-cloak
                            x-model="supplierSearch"
                            @input.debounce.500="searchSuppliers"
                            :disabled="selectedSupplier"
                            placeholder="Cerca fornitore..."
                            class="w-full mt-1 input"
                        >
                        {{-- dropdown risultati --}}
                        <div
                            x-show="supplierOptions.length"
                            x-cloak
                            class="mt-1 bg-white border rounded shadow max-h-40 overflow-y-auto"
                        >
                            <template x-for="option in supplierOptions" :key="option.id">
                                <div class="px-2 py-1 hover:bg-gray-200 cursor-pointer"
                                     @click="selectSupplier(option)">
                                    <span x-text="option.name"></span>
                                </div>
                            </template>
                        </div>
                        {{-- riepilogo fornitore scelto --}}
                        <template x-if="selectedSupplier">
                            <div class="mt-2 p-2 border rounded bg-gray-50">
                                <p class="font-semibold" x-text="selectedSupplier.name"></p>
                                <p class="text-xs" x-text="selectedSupplier.email"></p>
                                <p class="text-xs" x-text="'P.IVA: ' + selectedSupplier.vat_number"></p>
                                <p 
                                    class="text-xs" 
                                    x-text="
                                        selectedSupplier.address.via + ', ' + 
                                        selectedSupplier.address.city + ', ' + 
                                        selectedSupplier.address.postal_code + ', ' +
                                        selectedSupplier.address.country"
                                >
                                </p>
                                <button type="button" @click="selectedSupplier=null"
                                        class="text-xs text-red-600 mt-1">Cambia</button>
                            </div>
                        </template>
                    </div>

                    {{-- Valore totale (readonly) --}}
                    <div>
                        <label class="block text-sm font-medium">Valore ordine (€)</label>
                        <input type="text" :value="formatCurrency(total)"
                               class="w-full mt-1 input bg-gray-100" readonly>
                    </div>
                </div>
            </div>

            {{-- ▼ Sezione righe ordine --}}
            <div class="border-t pt-4 mt-4">
                <h4 class="font-semibold mb-2">Righe dell'ordine</h4>

                <template x-if="!lines.length">
                    <p class="text-sm text-gray-500">Nessuna riga aggiunta.</p>
                </template>

                <table x-show="lines.length" x-cloak
                       class="w-full text-sm border divide-y mt-2">
                    <thead class="text-left bg-gray-100">
                        <tr>
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
                                <td class="px-2 py-1" x-text="line.component.name"></td>
                                <td class="px-2 py-1 text-right" x-text="line.qty"></td>
                                <td class="px-2 py-1" x-text="line.unit"></td>
                                <td class="px-2 py-1 text-right"
                                    x-text="formatCurrency(line.price)"></td>
                                <td class="px-2 py-1 text-right"
                                    x-text="formatCurrency(line.subtotal)"></td>
                                <td class="px-2 py-1 text-center">
                                    <button type="button" @click="removeLine(idx)"
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- ▼ Aggiunta nuova riga --}}
            <div class="border-t pt-4 mt-4">
                <h4 class="font-semibold mb-2">Aggiungi componente</h4>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Ricerca componente --}}
                    <div>
                        <label class="block text-sm font-medium">Ricerca componente</label>
                        <input type="text" x-model="componentSearch"
                               @input.debounce.500="searchComponents"
                               placeholder="Cerca componente..."
                               class="w-full mt-1 input">
                        {{-- dropdown risultati --}}
                        <div
                            x-show="componentOptions.length"
                            x-cloak
                            class="mt-1 bg-white border rounded shadow max-h-40 overflow-y-auto"
                        >
                            <template x-for="option in componentOptions" :key="option.id">
                                <div class="px-2 py-1 hover:bg-gray-200 cursor-pointer"
                                     @click="selectComponent(option)">
                                    <span x-text="option.code"></span> —
                                    <span x-text="option.name"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Quantità --}}
                    <div>
                        <label class="block text-sm font-medium">Quantità</label>
                        <input type="number" min="1" x-model.number="quantity"
                               class="w-full mt-1 input">
                        <p class="text-xs mt-1"
                           x-text="unit ? 'Unità: ' + unit : ''"></p>
                    </div>

                    {{-- Prezzo + pulsante aggiungi --}}
                    <div class="flex flex-col justify-end">
                        <label class="block text-sm font-medium">Prezzo (€)</label>
                        <input type="text" x-model="price"
                               class="w-full mt-1 input bg-gray-100" readonly>

                        <button type="button"
                                class="mt-3 inline-flex items-center justify-center
                                       px-3 py-1.5 bg-emerald-600 rounded-md text-xs
                                       font-semibold text-white uppercase hover:bg-emerald-500"
                                :disabled="!selectedComponent || !quantity"
                                @click="addLine">
                            <i class="fas fa-plus-square mr-1"></i> Aggiungi componente
                        </button>
                    </div>
                </div>
            </div>

            {{-- ▼ Salva ordine --}}
            <div class="flex justify-end border-t pt-4 mt-4">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-purple-600
                               rounded-md text-sm font-semibold text-white uppercase
                               hover:bg-purple-500">
                    <i class="fas fa-save mr-2"></i> Salva ordine
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ─────────────────────────────────────────────────────────────────── --}}
{{--  Script Alpine: funzioni & stato del modale                         --}}
{{--  (Puoi spostarlo in un file .js dedicato se preferisci)             --}}
<script>
function supplierOrderModal() {
    return {
        /* === Stato base === */
        show      : false,
        editMode  : false,
        orderId   : null,

        /* === Dati ordine === */
        delivery_date : '',
        order_number  : '',       // < = generato lato server
        /* Fornitore */
        supplierSearch  : '',
        supplierOptions : [],
        selectedSupplier: null,

        /* === Righe ordine === */
        lines            : [],
        componentSearch  : '',
        componentOptions : [],
        selectedComponent: null,
        quantity         : 1,
        unit             : '',
        price            : 0,

        /* === Apertura / chiusura === */
        open(id = null) {
            this.show     = true;
            this.editMode = id !== null;
            this.orderId  = id;

            if (this.editMode) {
                this.fetchOrder(id);       // → carica dati esistenti
            } else {
                this.resetForm();          // → nuovo ordine
            }
        },

        close() { this.show = false },

        resetForm() {
            this.delivery_date      = '';
            this.order_number       = '—';
            this.selectedSupplier   = null;
            this.supplierSearch     = '';
            this.lines              = [];
            this.selectedComponent  = null;
            this.componentSearch    = '';
            this.quantity           = 1;
            this.unit               = '';
            this.price              = 0;
        },

        /* === Ricerca fornitori === */
        async searchSuppliers() {
            // minimo 2 caratteri prima di interrogare l’API
            if (this.supplierSearch.trim().length < 2) { 
                this.supplierOptions = [];
                return;
            }

            try {
                const resp = await fetch(
                    `/suppliers/search?q=${encodeURIComponent(this.supplierSearch.trim())}`,
                    {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'          // invia i cookie di sessione/XSRF
                    }
                );

                if (! resp.ok) throw new Error('HTTP ' + resp.status);

                this.supplierOptions = await resp.json();   // [{id,name,email}, …]
            } catch (err) {
                console.error('Autocomplete fornitori fallito:', err);
                this.supplierOptions = [];
            }
        },

        selectSupplier(s) {
            this.selectedSupplier = s
            this.supplierOptions  = []
        },

        /* === Ricerca componenti === */
        async searchComponents() {
            if (this.componentSearch.length < 2) { this.componentOptions = []; return }
            // TODO: fetch reale
        },
        selectComponent(c) {
            this.selectedComponent = c
            this.unit  = c.unit
            this.price = c.price
            this.componentOptions = []
        },

        /* === Gestione righe === */
        addLine() {
            if (!this.selectedComponent || this.quantity <= 0) return
            this.lines.push({
                component: this.selectedComponent,
                qty      : this.quantity,
                unit     : this.unit,
                price    : this.price,
                subtotal : this.quantity * this.price
            })
            /* reset campi componente */
            this.selectedComponent = null
            this.componentSearch   = ''
            this.quantity          = 1
            this.unit              = ''
            this.price             = 0
        },
        removeLine(i) { this.lines.splice(i,1) },

        /* === Helpers === */
        get total() {
            return this.lines.reduce((t,l) => t + l.subtotal, 0)
        },
        formatCurrency(v) {
            return Intl.NumberFormat('it-IT',{minimumFractionDigits:2}).format(v)
        },

        /* === Salvataggio === */
        async save() {
            if (!this.selectedSupplier || !this.delivery_date || !this.lines.length) {
                alert('Compila data consegna, fornitore e almeno una riga.'); return
            }
            /* Payload → backend */
            const payload = {
                supplier_id  : this.selectedSupplier.id,
                delivery_date: this.delivery_date,
                lines : this.lines.map(l => ({
                    component_id: l.component.id,
                    quantity    : l.qty,
                    price       : l.price
                }))
            }
            // TODO: POST/PUT (Axios, fetch, Livewire…)
            console.log('Payload ordine', payload)
            this.close()
        },

        /* === Carica ordine esistente === */
        async fetchOrder(id) {
            // TODO: GET dati ordine per edit
        }
    }
}
</script>
