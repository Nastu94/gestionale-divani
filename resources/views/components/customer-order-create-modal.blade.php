{{-- resources/views/components/customer-order-create-modal.blade.php --}}
{{-- =========================================================================
 |  Modale Crea / Modifica Ordine Cliente
 |  – PHP 8.4 / Laravel 12 – completamente commentato
 |  – front-end only: gli endpoint chiamati in fetch saranno implementati
 |    nella prossima fase (controller + rotte API)
 |  Basato su supplier-order-create-modal.blade.php → adattato a clienti /
 |  prodotti (stessa UX, stessi helper).
 |  Permesso minimo: orders.customer.create
 ========================================================================= --}}
<div
    x-data="customerOrderModal()"
    x-on:guest-created.window="handleGuest($event.detail)"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
    {{-- ascolta l’evento lanciato dalla toolbar della lista --}}
    x-init="window.addEventListener('open-customer-order-modal', e => open(e.detail?.orderId));"
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
                <span x-show="!editMode" x-cloak>Crea Ordine Cliente</span>
                <span x-show="editMode"  x-cloak>Modifica Ordine #<span x-text="orderId"></span></span>
            </h3>
            <button @click="close" class="text-gray-500 hover:text-gray-800"><i class="fas fa-times"></i></button>
        </div>

        {{-- ================= FORM ================= --}}
        <form @submit.prevent="editMode ? update() : save()" class="space-y-4">
            {{-- ========= DATI TESTATA ========= --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Colonna SX --}}
                <div class="space-y-4">
                    {{-- Numero ordine (progressivo riservato) --}}
                    <div>
                        <label class="block text-sm font-medium">N. ordine</label>
                        <input type="text" x-model="order_number" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100" readonly>
                    </div>

                    {{-- Selezione cliente --}}
                    <div class="relative">
                        <label x-show="!selectedCustomer" x-cloak class="block text-sm font-medium">Cliente</label>

                        <input
                            type="text"
                            x-show="!selectedCustomer"
                            x-cloak
                            x-model="customerSearch"
                            @input.debounce.500="searchCustomers"
                            placeholder="Cerca cliente…"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100"
                        >

                        {{-- Dropdown risultati --}}
                        <div
                            x-show="customerOptions.length"
                            x-cloak
                            class="absolute z-50 w-full mt-1 bg-white border rounded shadow max-h-40 overflow-y-auto"
                        >
                            <template x-for="(option, idx) in customerOptions" :key="option.id + '-' + idx">
                                <div class="px-2 py-1 hover:bg-gray-200 cursor-pointer"
                                     @click="selectCustomer(option)">
                                    <span class="text-xs" x-text="option.company + ' - ' + option.shipping_address"></span>
                                </div>
                            </template>
                        </div>

                        {{-- link crea guest --}}
                        <div class="mt-1">
                            <button type="button"
                                    class="text-xs text-emerald-700 hover:underline"
                                    @click.stop="window.dispatchEvent(new CustomEvent('open-occasional-customer-modal'))"
                                    x-show="showGuestButton && !selectedCustomer"
                                    x-cloak>
                                + Nuovo cliente occasionale
                            </button>
                        </div>

                        {{-- Riepilogo cliente scelto --}}
                        <template x-if="selectedCustomer">
                            <div class="mt-2 p-2 border rounded bg-gray-50">
                                <p class="font-semibold" x-text="selectedCustomer.company"></p>

                                <template x-if="selectedCustomer.email">
                                    <p class="text-xs" x-text="selectedCustomer.email"></p>
                                </template>

                                <template x-if="selectedCustomer.vat_number">
                                    <p class="text-xs" x-text="'P.IVA: ' + selectedCustomer.vat_number"></p>
                                </template>

                                <template x-if="selectedCustomer.tax_code && !selectedCustomer.vat_number">
                                    <p class="text-xs" x-text="'C.F.: ' + selectedCustomer.tax_code"></p>
                                </template>

                                <template x-if="selectedCustomer.shipping_address">
                                    <p class="text-xs" x-text="selectedCustomer.shipping_address"></p>
                                </template>

                                <button type="button"
                                        x-show="!editMode"
                                        @click="selectedCustomer=null; showGuestButton=true"
                                        class="text-xs text-red-600 mt-1">
                                    Cambia
                                </button>
                            </div>
                        </template>

                    </div>
                </div>

                {{-- Colonna DX --}}
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Data consegna richiesta</label>
                        <input type="date" x-model="delivery_date" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Valore ordine (€)</label>
                        <input type="text" :value="formatCurrency(total)" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100" readonly>
                    </div>
                </div>
            </div>

            {{-- ========= RIGHE DELL'ORDINE ========= --}}
            <div class="border-t pt-4">
                {{-- tabella righe già inserite --}}
                <table class="table-auto w-full text-xs mb-4">
                    <thead class="bg-gray-200 dark:bg-gray-800">
                        <tr>
                            <th class="px-2 py-1 text-left">Prodotto</th>
                            <th class="px-2 py-1 text-left">Tessuto</th>
                            <th class="px-2 py-1 w-20 text-right">Q.tà</th>
                            <th class="px-2 py-1 w-24 text-right">Prezzo €</th>
                            <th class="px-2 py-1 w-24 text-right">Sub-Tot €</th>
                            <th class="px-2 py-1 w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(l,idx) in lines" :key="'line'+idx">
                            <tr class="border-b">
                                <td class="px-2 py-1" x-text="l.product.sku + ' — ' + l.product.name"></td>
                                <td class="px-2 py-1">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-gray-100">
                                            <i class="fas fa-tshirt text-[11px]"></i>
                                            <span x-text="l.fabric_name || '—'"></span>
                                        </span>
                                        <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-gray-100">
                                            <i class="fas fa-palette text-[11px]"></i>
                                            <span x-text="l.color_name || '—'"></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-2 py-1 text-right" x-text="l.qty"></td>
                                <td class="px-2 py-1 text-right" x-text="formatCurrency(l.price)"></td>
                                <td class="px-2 py-1 text-right" x-text="formatCurrency(l.subtotal)"></td>
                                <td class="px-2 py-1 text-center flex space-x-1">
                                    <button type="button" @click="editLine(idx)" class="text-indigo-600"><i class="fas fa-pen"></i></button>
                                    <button type="button" @click="removeLine(idx)" class="ml-1 text-red-600"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- CARRENZE COMPONENTI --}}
            <div x-show="availabilityOk === false"
                x-cloak
                class="border-t pt-4 mt-4">
                <h4 class="font-semibold text-red-700 text-sm mb-2">
                    Componenti mancanti
                </h4>

                <table class="w-full text-xs border">
                    <thead class="bg-red-100">
                        <tr>
                            <th class="px-2 py-1 text-left">Codice</th>
                            <th class="px-2 py-1 text-left">Descrizione</th>
                            <th class="px-2 py-1 text-right">Necessari</th>
                            <th class="px-2 py-1 text-right">Disponibili</th>
                            <th class="px-2 py-1 text-right">In arrivo</th>
                            <th class="px-2 py-1 text-right">Mancano</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in shortage" :key="row.component_id">
                            <tr class="border-t">
                                <td class="px-2 py-1" x-text="row.code"></td>
                                <td class="px-2 py-1" x-text="row.description"></td>
                                <td class="px-2 py-1 text-right" x-text="row.needed"></td>
                                <td class="px-2 py-1 text-right" x-text="row.available"></td>
                                <td class="px-2 py-1 text-right" x-text="Number(row.incoming) + Number(row.my_incoming)"></td>
                                <td class="px-2 py-1 text-right font-semibold text-red-700" x-text="row.shortage"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- ========= AGGIUNGI PRODOTTO ========= --}}
            <div class="border-t pt-4 mt-4"
                 x-bind:class="canAddLines ? '' : 'opacity-50 pointer-events-none select-none'">
                <h4 class="font-semibold mb-2">Aggiungi prodotto</h4>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Ricerca prodotto --}}
                    <div class="relative">
                        <label class="block text-sm font-medium">Ricerca prodotto</label>

                        <input type="text"
                               x-model="productSearch"
                               @input.debounce.500="searchProducts"
                               placeholder="Cerca prodotto…"
                               class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100"
                               x-show="!selectedProduct"
                               x-cloak
                               :disabled="!canAddLines">

                        <div x-show="productOptions.length"
                             x-cloak
                             class="absolute z-50 w-full mt-1 bg-white border rounded shadow max-h-40 overflow-y-auto">
                            <template x-for="option in productOptions" :key="option.id">
                                <div class="px-2 py-1 hover:bg-gray-200 cursor-pointer"
                                     @click="selectProduct(option)">
                                    <span class="text-xs" x-text="option.sku + ' — '"></span>
                                    <span class="text-xs" x-text="option.name"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Riepilogo prodotto scelto --}}
                        <template x-if="selectedProduct">
                            <div class="mt-2 p-2 border rounded bg-gray-50">
                                <p><strong x-text="selectedProduct.sku"></strong> — <span x-text="selectedProduct.name"></span></p>
                                <button type="button" @click="selectedProduct=null"
                                        class="text-xs text-red-600 mt-1">Cambia</button>
                            </div>
                        </template>
                    </div>

                    {{-- Quantità --}}
                    <div>
                        <label class="block text-sm font-medium">Quantità</label>
                        <input type="number" min="1" x-model.number="quantity" @input.debounce.250ms="reprice()"
                               class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100" :disabled="!canAddLines">
                    </div>

                    {{-- Prezzo + pulsante --}}
                    <div class="flex flex-col">
                        <label class="block text-sm font-medium">Prezzo (€)</label>
                        <input type="number" min="0" step="0.01" x-model.number="price"
                               class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100" :disabled="!canAddLines">
                    </div>
                </div>

                {{-- [AGGIUNTA] Selezione variabili per la riga corrente --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                    {{-- Tessuto --}}
                    <div>
                        <label class="block text-sm font-medium">Tessuto</label>
                        <select class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm"
                                x-model.number="fabric_id"
                                @change="reprice()"
                                :disabled="!selectedProduct || fabricOptions.length === 0">
                            <option value="">—</option>
                            <template x-for="f in fabricOptions" :key="'f-'+f.id">
                                <option :value="f.id" x-text="f.name"></option>
                            </template>
                        </select>
                        <p class="text-[11px] text-amber-600 mt-1" x-show="selectedProduct && fabricOptions.length===0" x-cloak>
                            Nessun tessuto in whitelist per questo prodotto.
                        </p>
                    </div>

                    {{-- Colore --}}
                    <div>
                        <label class="block text-sm font-medium">Colore</label>
                        <select class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm"
                                x-model.number="color_id"
                                @change="reprice()"
                                :disabled="!selectedProduct || colorOptions.length === 0">
                            <option value="">—</option>
                            <template x-for="c in colorOptions" :key="'c-'+c.id">
                                <option :value="c.id" x-text="c.name"></option>
                            </template>
                        </select>
                        <p class="text-[11px] text-amber-600 mt-1" x-show="selectedProduct && colorOptions.length===0" x-cloak>
                            Nessun colore in whitelist per questo prodotto.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="button"
                            class="mt-3 inline-flex items-center justify-center
                                    px-3 py-1.5 rounded-md text-xs font-semibold text-white uppercase
                                    bg-emerald-600 hover:bg-emerald-500"
                            :disabled="!canAddLines || !selectedProduct || quantity <= 0"
                            @click="addLine">
                        <i class="fas fa-plus-square mr-1"></i> Aggiungi prodotto
                    </button>
                </div>

            </div>

            {{-- ========= SALVA ========= --}}
            <div class="flex justify-end border-t pt-4 mt-4 space-x-2">
                <button type="button"
                        @click="close()"
                        class="inline-flex items-center px-3 py-1.5 border rounded-md text-xs font-semibold
                            text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                    Annulla
                </button>

                {{-- Verifica disponibilità --}}
                <button type="button"
                        class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-semibold
                            text-white uppercase bg-blue-600 hover:bg-blue-500
                            disabled:opacity-50"
                        :disabled="checking"
                        @click="checkAvailability">
                    <i class="fas fa-circle-notch fa-spin mr-2" x-show="checking"></i>
                    Verifica disponibilità
                </button>

                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-purple-600
                            rounded-md text-sm font-semibold text-white uppercase
                            hover:bg-purple-500"
                        :disabled="availabilityOk === null || checking">
                    <i class="fas fa-save mr-2"></i>
                    <span x-text="editMode ? 'Modifica Ordine' : 'Salva Ordine'"></span>
                </button>
            </div>
        </form>

        {{-- modale per cliente occasionale --}}
        <x-occasional-customer-modal />
    </div>
</div>

{{-- ===================== SCRIPT ALPINE ===================== --}}
<script>
    if (!window.GD_VARIABLE_OPTIONS) {
        fetch('/products/variables/options', { headers:{ Accept:'application/json' }})
            .then(r => r.json())
            .then(data => {
            // atteso: { fabrics:[{id,name},...], colors:[{id,name},...] }
            window.GD_VARIABLE_OPTIONS = data || {fabrics:[], colors:[]};
            })
            .catch(() => { window.GD_VARIABLE_OPTIONS = {fabrics:[], colors:[]}; });
    }

    window.GD_findName = (arr, id) =>
        (arr || []).find(x => Number(x.id) === Number(id))?.name ?? null;
    
    function customerOrderModal() {
        return {
            /* ==== Stato base ==== */
            show      : false,
            editMode  : false,
            orderId   : null,
            showGuestButton : true, 

            /* ==== Header ordine ==== */
            delivery_date    : '',
            order_number_id  : null,
            order_number     : '—',

            /* ==== Cliente ==== */
            customerSearch   : '',
            customerOptions  : [],
            selectedCustomer : null,
            occasional_customer_id : null,

            /* ==== Righe ==== */
            lines            : [],
            productSearch    : '',
            productOptions   : [],
            selectedProduct  : null,
            price            : 0,
            quantity         : 1,

            /* ==== Variabili di riga (nuove) ==== */
            fabricOptions : [],   // opzioni tessuto filtrate dalla whitelist prodotto
            colorOptions  : [],   // opzioni colore filtrate dalla whitelist prodotto
            fabric_id     : '',   // selezione corrente (tessuto)
            color_id      : '',   // selezione corrente (colore)

            availabilityOk : null,     // null = non ancora verificato
            shortage       : [],       // array componenti mancanti
            poLinks        : [],       // ordini fornitore creati
            checking       : false,    // spinner Verifica

            /* --- nuovo metodo --- */
            handleGuest(guest) {
                /* compone indirizzo di spedizione (opzionale) */
                guest.shipping_address = [
                    guest.address,
                    guest.postal_code && guest.city ? `${guest.postal_code} ${guest.city}` : guest.city,
                    guest.province,
                    guest.country
                ].filter(Boolean).join(', ');

                this.selectedCustomer        = guest;
                this.occasional_customer_id  = guest.id;
                this.customer_id             = null;     // mutua esclusione
                this.showGuestButton         = false;
            },

            /* ==== Getter ==== */
            get canAddLines() { return this.selectedCustomer && this.delivery_date; },
            get total()       { return this.lines.reduce((t,l)=>t + l.subtotal, 0); },

            /* ==== Apertura / chiusura ==== */
            open(id=null){
                this.show     = true;
                this.editMode = !!id;
                this.orderId  = id;

                if (this.editMode) {
                    this.fetchOrder(id);
                } else {
                    this.resetForm();
                    this.reserveNumber();
                }
            },

            close(){ this.show=false; this.resetForm(); },

            resetForm(){
                this.delivery_date    = '';
                this.selectedCustomer = null;
                this.occasional_customer_id  = null;
                this.customerOptions  = [];
                this.customerSearch   = '';
                this.lines            = [];
                this.selectedProduct  = null;
                this.productSearch    = '';
                this.price            = 0;
                this.quantity         = 1;
                this.order_number_id  = null;
                this.order_number   = '—';
                this.availabilityOk = null;
                this.shortage       = [];
                this.poLinks        = [];
                this.checking       = false;
                this.fabricOptions=[]; this.colorOptions=[];
                this.fabric_id=''; this.color_id='';
            },

            /* ==== Prenota progressivo ==== */
            reserveNumber(){
                fetch('/order-numbers/reserve', {
                    method : 'POST',
                    headers: {
                        'Accept':'application/json','Content-Type':'application/json',
                        'X-CSRF-TOKEN':document.querySelector('meta[name=\"csrf-token\"]').content
                    },
                    credentials:'same-origin',
                    body: JSON.stringify({ type:'customer' })
                })
                .then(r=>r.json()).then(j=>{ this.order_number_id=j.id; this.order_number=j.number; })
                .catch(()=>{ this.order_number_id=null; this.order_number='—'; });
            },

            /* ==== Ricerca clienti ==== */
            async searchCustomers(){
                if (this.customerSearch.trim().length < 2) { this.customerOptions=[]; return; }
                try{
                    const r = await fetch(`/customers/search?q=${encodeURIComponent(this.customerSearch.trim())}`,
                                        { headers:{Accept:'application/json'}, credentials:'same-origin' });
                    if(!r.ok) throw new Error(r.status);
                    this.customerOptions = await r.json();
                }catch{ this.customerOptions=[]; }
            },

            selectCustomer(o){ 
                this.selectedCustomer=o; 
                this.customerOptions=[];
                this.occasional_customer_id = null; 
            },

            /* ==== Ricerca prodotti ==== */
            async searchProducts() {
                if (this.productSearch.trim().length < 2) {
                    this.productOptions = []; return;
                }

                // id cliente "reale": per guest (occasionale) mettilo a null
                const custId = this.occasional_customer_id ? '' : (this.selectedCustomer?.id ?? '');

                const qs = new URLSearchParams({
                    q: this.productSearch.trim(),
                    ...(custId ? { customer_id: String(custId) } : {}),
                    ...(this.delivery_date ? { date: this.delivery_date } : {}),
                });

                try {
                    const r = await fetch(`/products/search?${qs.toString()}`, {
                        headers:{ Accept:'application/json' }, credentials:'same-origin'
                    });
                    if (!r.ok) throw new Error(r.status);
                    this.productOptions = await r.json(); // ogni option può avere effective_price & price_source
                } catch {
                    this.productOptions = [];
                }
            },

            selectProduct(option) {
                this.selectedProduct = option;
                this.productOptions  = [];
                // Default prezzo da resolver se disponibile, altrimenti base
                const p = option.effective_price ?? option.price ?? 0;
                this.price = Number(p);
                this.loadProductWhitelist(option.id);
            },

            /* ==== Gestione righe ==== */
            addLine(){
                if(!this.selectedProduct || this.quantity<=0) return;
                const qty  = Number(this.quantity || 1);
                const unit = Number(this.price || 0);
                this.lines.push({
                    product  : this.selectedProduct,
                    qty      : qty,
                    price    : unit,
                    subtotal : unit * qty,
                    fabric_id   : this.fabric_id || null,
                    color_id    : this.color_id  || null,
                    fabric_name : window.GD_findName(window.GD_VARIABLE_OPTIONS.fabrics, this.fabric_id),
                    color_name  : window.GD_findName(window.GD_VARIABLE_OPTIONS.colors,  this.color_id),
                });
                this.availabilityOk = null; 
                this.total = this.lines.reduce((s,l)=>s+(l.subtotal||0),0);
                // reset input riga
                this.selectedProduct=null; this.productSearch=''; this.price=0; this.quantity=1; this.fabric_id=null; this.color_id=null;
            },

            editLine(i){
                const l = this.lines.splice(i,1)[0];
                this.selectedProduct    =l.product; 
                this.price              =l.price; 
                this.quantity           =l.qty; 
                this.fabric_id          = l.fabric_id ?? '';
                this.color_id           = l.color_id  ?? '';
                this.loadProductWhitelist(l.product.id);
                this.productSearch='';
                this.availabilityOk = null; 
            },

            removeLine(i){ this.lines.splice(i,1); this.availabilityOk = null; },

            /* ==== Helpers ==== */
            formatCurrency(v){ return Intl.NumberFormat('it-IT',{minimumFractionDigits:2}).format(v||0); },

            /* ══════════ Submit unico (create / update) ══════════ */
            async submit() {
                /* 1️⃣  validazioni front-end rapide */
                if (!this.selectedCustomer || !this.delivery_date || !this.lines.length) {
                    alert('Compila data consegna, cliente e almeno una riga.'); return
                }
                if (this.availabilityOk === null) {
                    alert('Esegui prima la verifica disponibilità.'); return
                }

                /* 2️⃣  payload comune */
                const payload = {
                    /* header */
                    order_number_id : this.order_number_id,
                    customer_id     : this.occasional_customer_id ? null : this.selectedCustomer.id,
                    occasional_customer_id : this.occasional_customer_id ?? null,
                    delivery_date   : this.delivery_date,
                    shipping_address: this.selectedCustomer.shipping_address,
                    /* righe */
                    lines : this.lines.map(l => ({
                        product_id : l.product.id,
                        quantity   : l.qty,
                        price      : l.price,
                        fabric_id  : l.fabric_id || null,
                        color_id   : l.color_id  || null,
                    }))
                }

                /* 3️⃣  metodo + url in base alla modalità */
                const url    = this.editMode
                            ? `/orders/customer/${this.orderId}`
                            : '/orders/customer'
                const method = this.editMode ? 'PUT' : 'POST'

                /* 4️⃣  invio */
                try {
                    const r = await fetch(url, {
                        method,
                        headers : {
                            Accept         : 'application/json',
                            'Content-Type' : 'application/json',
                            'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                        },
                        credentials : 'same-origin',
                        body        : JSON.stringify(payload)
                    })
                    if (!r.ok) throw new Error(await r.text())

                    /* messaggio di conferma */
                    const j = await r.json()
                    if (j.po_numbers?.length) {
                        alert('Ordine cliente salvato.\nSono stati creati i seguenti ordini fornitore: ' + j.po_numbers.join(', '));
                    } else {
                        alert('Ordine salvato con successo.')
                    }

                    this.close()
                    window.location.reload()          // rinfresca la lista

                } catch (e) {
                    console.error('Errore salvataggio', e)
                    alert('Errore nel salvataggio.')
                }
            },
            
            save() {
                this.submit();
            },

            update() {
                this.submit();
            },
            
            /* ==== Fetch ordine esistente (edit) ==== */
            async fetchOrder(id) {
                try {
                    /* 1️⃣  nuovo endpoint RESTful  */
                    const r = await fetch(`/orders/customer/${id}/edit`, {
                        headers     : { 'Accept':'application/json' }, // forza JSON
                        credentials : 'same-origin'
                    })
                    if (!r.ok) throw new Error(r.status)
                    const o = await r.json()

                    /* 2️⃣  popola form  */
                    this.orderId        = o.id
                    this.order_number   = o.number      // restituito dal controller edit()
                    this.order_number_id= o.order_number_id
                    this.delivery_date  = o.delivery_date
                    this.selectedCustomer = o.customer ?? o.occ_customer      // oggetto completo
                    this.selectedCustomer.shipping_address = o.shipping_address ?? '' // indirizzo spedizione
                    this.customerSearch   = this.selectedCustomer?.company ?? ''   // → input popolato

                    this.lines = o.lines.map(l => ({
                        product  : { id:l.product_id, sku:l.sku, name:l.name },
                        qty      : l.quantity,
                        price    : Number(l.price),
                        subtotal : l.quantity * l.price
                    }))

                    /* reset campi “nuova riga” */
                    this.selectedProduct = null
                    this.productSearch   = ''
                    this.price           = 0
                    this.quantity        = 1

                } catch (e) {
                    console.error('Impossibile caricare ordine', e)
                    alert('Errore nel caricamento dei dati ordine.')
                    this.close()
                }
            },

            async checkAvailability () {
                this.checking       = true;
                this.availabilityOk = null;

                try {
                    const payload = {
                        order_id      : this.editMode ? this.orderId : null,
                        delivery_date : this.delivery_date,
                        lines         : this.lines.map(l => ({
                            product_id : l.product.id,
                            quantity   : l.qty,
                            fabric_id  : l.fabric_id || null,
                            color_id   : l.color_id  || null,
                        }))
                    };

                    const r = await fetch('/orders/check-components', {
                        method  : 'POST',
                        headers : {
                            Accept         : 'application/json',
                            'Content-Type' : 'application/json',
                            'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                        },
                        credentials : 'same-origin',
                        body        : JSON.stringify(payload)
                    });

                    const j = await r.json();

                    this.availabilityOk = j.ok;
                    this.shortage       = j.shortage ?? [];

                    /* ALERT */
                    if (j.ok) {
                        alert('Tutti i componenti sono disponibili.');
                    } else {
                        alert('Componenti insufficienti: controlla la tabella sotto.');
                    }

                } catch (e) {
                    console.error(e);
                    alert('Errore nella verifica disponibilità');
                } finally {
                    this.checking = false;
                }
            },

            /**
             * Carica la whitelist del prodotto selezionato e popola le select.
             * Usa l'endpoint già esistente GET /products/{product}/variables
             */
            async loadProductWhitelist(productId) {
                if (!productId) { 
                    this.fabricOptions=[]; 
                    this.colorOptions=[]; 
                    this.fabric_id=''; 
                    this.color_id=''; 
                    return; 
                }

                try {
                    const r = await fetch(`/products/${productId}/variables`, { headers: { Accept:'application/json' } });
                    if (!r.ok) throw new Error(String(r.status));
                    const js = await r.json();

                    // 2) prendo i cataloghi globali e filtro sugli ID consentiti
                    const allF = (window.GD_VARIABLE_OPTIONS && window.GD_VARIABLE_OPTIONS.fabrics) || [];
                    const allC = (window.GD_VARIABLE_OPTIONS && window.GD_VARIABLE_OPTIONS.colors)  || [];

                    const allowedF = new Set((js.fabric_ids || []).map(Number));
                    const allowedC = new Set((js.color_ids  || []).map(Number));

                    this.fabricOptions = allF.filter(f => allowedF.has(Number(f.id)));
                    this.colorOptions  = allC.filter(c => allowedC.has(Number(c.id)));

                    // 3) imposto i default (solo se compresi nella whitelist)
                    const defF = Number(js.default_fabric_id || 0);
                    const defC = Number(js.default_color_id || 0);

                    this.fabric_id = (defF && allowedF.has(defF)) ? defF : (this.fabricOptions[0]?.id ?? '');
                    this.color_id  = (defC && allowedC.has(defC)) ? defC : (this.colorOptions[0]?.id ?? '');

                    this.reprice();
                } catch (e) {
                    console.error('variables load failed', e);
                    // in caso di errore, disabilita entrambe le select
                    this.fabricOptions=[]; this.colorOptions=[];
                    this.fabric_id=''; this.color_id='';
                }
            },

            /* Ricalcolo prezzo lato server (consigliato) */
            _priceAbort: null,
            async reprice() {
                if (!this.selectedProduct) return;
                try {
                    if (this._priceAbort) this._priceAbort.abort();
                    this._priceAbort = new AbortController();

                    const body = {
                        product_id: this.selectedProduct.id,
                        qty: this.quantity || 1,
                        fabric_id: this.fabric_id || null,
                        color_id:  this.color_id  || null,
                        customer_id: this.occasional_customer_id ? null : (this.selectedCustomer?.id ?? null),
                    };

                    const r = await fetch('/orders/line-quote', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        },
                        body: JSON.stringify(body),
                        signal: this._priceAbort.signal,
                    });
                    if (!r.ok) throw new Error('pricing_failed');
                    const q = await r.json();

                    this.price = Number(q.unit_price ?? q.effective_price ?? 0);
                    this.total = this.lines.reduce((s,l)=>s+(l.subtotal||0),0) + this.price * (this.quantity||1);
                } catch(e) {
                    if (e.name === 'AbortError') return; // ignoriamo richieste precedenti
                }
            },
        };
    }
</script>
