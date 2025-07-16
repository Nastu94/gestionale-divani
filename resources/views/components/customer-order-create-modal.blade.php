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
                            <template x-for="option in customerOptions" :key="option.id">
                                <div class="px-2 py-1 hover:bg-gray-200 cursor-pointer"
                                     @click="selectCustomer(option)">
                                    <span class="text-xs" x-text="option.company"></span>
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
                                <td class="px-2 py-1 text-right" x-text="row.incoming"></td>
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
                        <input type="number" min="1" x-model.number="quantity"
                               class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100" :disabled="!canAddLines">
                    </div>

                    {{-- Prezzo + pulsante --}}
                    <div class="flex flex-col">
                        <label class="block text-sm font-medium">Prezzo (€)</label>
                        <input type="number" min="0" step="0.01" x-model.number="price"
                               class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100" :disabled="!canAddLines">

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

        /* ==== Righe ==== */
        lines            : [],
        productSearch    : '',
        productOptions   : [],
        selectedProduct  : null,
        price            : 0,
        quantity         : 1,

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
        selectCustomer(o){ this.selectedCustomer=o; this.customerOptions=[]; },

        /* ==== Ricerca prodotti ==== */
        async searchProducts() {
            if (this.productSearch.trim().length < 2) {          // evita hit con 1 carattere
                this.productOptions = [];
                return;
            }

            try {
                const r = await fetch(
                    `/products/search?q=${encodeURIComponent(this.productSearch.trim())}`,
                    { headers:{Accept:'application/json'}, credentials:'same-origin' }
                );
                if (!r.ok) throw new Error(r.status);
                this.productOptions = await r.json();            // [{id, sku, name, price}, ...]
            } catch {
                this.productOptions = [];
            }
        },

        selectProduct(p){ this.selectedProduct=p; this.productOptions=[]; this.price=p.price ?? 0; },

        /* ==== Gestione righe ==== */
        addLine(){
            if(!this.selectedProduct || this.quantity<=0) return;
            this.lines.push({
                product  : this.selectedProduct,
                qty      : this.quantity,
                price    : this.price,
                subtotal : this.price * this.quantity
            });
            this.availabilityOk = null; 
            // reset input riga
            this.selectedProduct=null; this.productSearch=''; this.price=0; this.quantity=1;
        },
        editLine(i){
            const l = this.lines.splice(i,1)[0];
            this.selectedProduct=l.product; this.price=l.price; this.quantity=l.qty; this.productSearch='';
            this.availabilityOk = null; 
        },
        removeLine(i){ this.lines.splice(i,1); this.availabilityOk = null; },

        /* ==== Helpers ==== */
        formatCurrency(v){ return Intl.NumberFormat('it-IT',{minimumFractionDigits:2}).format(v||0); },

        /* ==== Salva ==== */
        async save () {

            if (!this.selectedCustomer || !this.delivery_date || !this.lines.length) {
                alert('Compila data consegna, cliente e almeno una riga.');
                return;
            }

            if (this.availabilityOk === null) {
                alert('Devi prima verificare la disponibilità dei componenti.');
                return;
            }

            const payload = {
                order_number_id : this.order_number_id,
                customer_id     : this.selectedCustomer.id,
                delivery_date   : this.delivery_date,
                lines : this.lines.map(l => ({
                    product_id : l.product.id,
                    quantity   : l.qty,
                    price      : l.price
                }))
            };

            this.saving = true;

            try {
                const r = await fetch('/orders/customer', {
                    method  : 'POST',
                    headers : {
                        Accept         : 'application/json',
                        'Content-Type' : 'application/json',
                        'X-CSRF-TOKEN' : document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials : 'same-origin',
                    body        : JSON.stringify(payload)
                });

                if (!r.ok) throw new Error(await r.text());
                const j = await r.json();

                if (j.po_ids && j.po_ids.length) {
                    alert('Ordine cliente salvato.\nCreati ordini fornitore: ' + j.po_ids.join(', '));
                } else {
                    alert('Ordine cliente salvato con successo.');
                }

                this.close();
                window.location.reload();

            } catch (e) {
                console.error('Errore salvataggio', e);
                alert('Errore nel salvataggio.');
            } finally {
                this.saving = false;
            }
        },

        /* ==== Fetch ordine esistente (edit) ==== */
        async fetchOrder(id){
            try{
                const r = await fetch(`/orders/customer/${id}/api`,
                                      { headers:{Accept:'application/json'}, credentials:'same-origin' });
                if(!r.ok) throw new Error(r.status);
                const o = await r.json();
                /* header */
                this.orderId        = o.id;
                this.order_number   = o.order_number;
                this.order_number_id= o.order_number_id;
                this.selectedCustomer = o.customer;
                this.delivery_date  = o.delivery_date;
                /* righe */
                this.lines = o.lines.map(l=>({
                    id       : l.id,
                    product  : l.product,
                    qty      : l.qty,
                    price    : Number(l.price),
                    subtotal : l.subtotal
                }));
                /* reset campi “nuova riga” */
                this.selectedProduct=null; this.productSearch=''; this.price=0; this.quantity=1;
            }catch(e){ console.error('Impossibile caricare ordine',e); alert('Errore caricamento.'); this.close(); }
        },

        /* ==== Aggiorna ordine esistente ==== */
        async update(){
            if(!this.lines.length){ alert('Serve almeno una riga'); return; }
            const payload = {
                delivery_date : this.delivery_date,
                lines         : this.lines.map(l=>({
                    id         : l.id ?? null,
                    product_id : l.product.id,
                    quantity   : l.qty,
                    price      : l.price
                }))
            };
            try{
                const r = await fetch(`/orders/customer/${this.orderId}`, {
                    method:'PUT',
                    headers:{
                        'Accept':'application/json','Content-Type':'application/json',
                        'X-CSRF-TOKEN':document.querySelector('meta[name=\"csrf-token\"]').content
                    },
                    credentials:'same-origin',
                    body: JSON.stringify(payload)
                });
                if(!r.ok) throw new Error(await r.text());
                this.close(); window.location.reload();
            }catch(e){ console.error('Errore update',e); alert('Errore durante la modifica.'); }
        },

        async checkAvailability () {
            this.checking       = true;
            this.availabilityOk = null;

            try {
                const payload = {
                    delivery_date : this.delivery_date,
                    lines : this.lines.map(l => ({
                        product_id : l.product.id,
                        quantity   : l.qty
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

    };
}
</script>
