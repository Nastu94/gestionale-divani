{{-- resources/views/pages/master-data/index-products.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Prodotti') }}</h2>
            <x-dashboard-tiles />
        </div>
        
        @if (session('success'))
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 10000)"
                x-show="show"
                x-transition.opacity.duration.500ms
                class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-2"
                role="alert"
            >
                <i class="fas fa-check-circle mr-2"></i>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 10000)"
                x-show="show"
                x-transition.opacity.duration.500ms
                class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-2"
                role="alert"
            >
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif
    </x-slot>
    <div class="py-6">
        <div x-data="productCrud()" class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                {{-- Pulsante “Nuovo” --}}
                <div class="flex justify-end m-2 p-2">
                    @if(auth()->user()->can('products.create'))
                        <button 
                            @click="openCreate"
                            class="inline-flex items-center m-2 px-3 py-1.5 bg-purple-600 rounded-md
                                        text-xs font-semibold text-white uppercase hover:bg-purple-500
                                        focus:outline-none focus:ring-2 focus:ring-purple-300 transition"
                        >
                            <i class="fas fa-plus mr-1"></i> Nuovo
                        </button>
                    @endif

                    {{-- Pulsante Estendi/Comprimi su tutta la tabella --}}
                    <button
                        type="button"
                        @click="extended = !extended"
                        class="inline-flex items-center m-2 px-3 py-1.5 bg-indigo-600 rounded-md text-xs font-semibold text-white uppercase
                            hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-900 transition"
                    >
                        <i class="fas p-1" :class="extended ? 'fa-compress' : 'fa-expand'"></i>
                        <span x-text="extended ? 'Comprimi tabella' : 'Estendi tabella'"></span>
                    </button>
                </div>

                {{-- Modale Create / Edit --}}
                <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black opacity-75"></div>
                    <div class="relative z-10 w-full max-w-3xl">
                        <x-product-create-modal 
                            :products="$products"
                            :components="$components" 
                        />
                    </div>
                </div>

                {{-- Modale Listino Prodotto --}}
                <template x-if="showPriceListModal">
                    <x-product-pricelist-modal />
                </template>

                {{-- Modale Prezzi Prodotto --}}
                <template x-if="showCustomerPriceModal">
                    <x-product-price-form-modal />
                </template>

                {{-- Modale Visualizza Distinta Base Prodotto --}}
                <template x-if="showViewModal">
                    <x-product-view-modal />
                </template>

                {{-- Modale Variabili Prodotto --}}
                <x-product-variables-modal />

                {{-- Tabella espandibile --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700">
                            <tr class="uppercase tracking-wider">
                                <x-th-menu
                                    field="sku"
                                    label="Codice Prodotto"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="products.index"
                                />
                                <x-th-menu
                                    field="name"
                                    label="Nome"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="products.index"
                                />
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left">Descrizione</th>
                                <x-th-menu
                                    x-show="extended"
                                    x-cloak
                                    field="price"
                                    label="Prezzo"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="products.index"
                                    align="right"
                                />
                                <x-th-menu
                                    field="is_active"
                                    label="Attivo"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    align="right"
                                    :filterable="false"
                                    reset-route="products.index"
                                />
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($products as $product)
                                @php
                                    $canEdit   = auth()->user()->can('products.update');
                                    $canDelete = auth()->user()->can('products.delete');
                                    $canCrud   = $canEdit || $canDelete;
                                @endphp

                                {{-- Riga principale --}}
                                <tr
                                    @if($canCrud || auth()->user()->can('products.view'))
                                        @click="openId = (openId === {{ $product->id }} ? null : {{ $product->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $product->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $product->sku }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $product->name }}</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 text-left">{{ $product->description ?? '—' }}</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">{{ $product->price ?? '—' }}€</td>
                                    <td class="px-6 py-2 text-center whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                            :class="{
                                                'bg-green-100 text-green-800': {{ $product->is_active ? 'true' : 'false' }},
                                                'bg-red-100 text-red-800': {{ $product->is_active ? 'false' : 'true' }}
                                            }"
                                        >
                                            {{ $product->is_active ? 'Sì' : 'No' }}
                                        </span>
                                    </td>
                                </tr>

                                {{-- Riga espansa con Modifica / Elimina / Estendi --}}
                                @if($canCrud || auth()->user()->can('products.view'))
                                <tr x-show="openId === {{ $product->id }}" x-cloak>
                                    <td
                                        :colspan="extended ? 5 : 3"
                                        class="px-6 py-3 bg-gray-200 dark:bg-gray-700"
                                    >
                                        <div class="flex items-center space-x-4 text-xs">
                                            @can('products.view')
                                                <button
                                                    type="button"
                                                    @click='openShow(@json($product))'
                                                    class="inline-flex items-center hover:text-indigo-600"
                                                >
                                                    <i class="fas fa-eye mr-1"></i> Visualizza
                                                </button>
                                            @endcan

                                            @if($canEdit)
                                                <button
                                                    type="button"
                                                    @click='openEdit(@json($product))'
                                                    class="inline-flex items-center hover:text-yellow-600"
                                                >
                                                    <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                                </button>
                                            @endif
                                            
                                            @can('product-prices.view')
                                                <button type="button"
                                                        @click='openPriceList(@json($product))'
                                                        class="inline-flex items-center hover:text-pink-600">
                                                    <i class="fas fa-list mr-1"></i> Listino
                                                </button>
                                            @endcan

                                            @canany(['product-prices.create', 'product-prices.update'])
                                                <button type="button"
                                                        @click='openPriceCreate(@json($product))'
                                                        class="inline-flex items-center hover:text-green-600">
                                                    <i class="fas fa-handshake mr-1"></i> Cliente
                                                </button>
                                            @endcanany
                                            
                                            @can('product-variables.update')
                                                <button type="button"
                                                        class="inline-flex items-center hover:text-orange-600"
                                                        x-data
                                                        @click="$dispatch('open-product-variables', { productId: {{ $product->id }} })">
                                                    <!-- icona semplice -->
                                                    <i class="fa-solid fa-swatchbook mr-1"></i> Variabili
                                                </button>
                                            @endcan

                                            @if($canDelete)
                                                @unless($product->trashed())
                                                    <form
                                                        action="{{ route('products.destroy', $product) }}"
                                                        method="POST"
                                                        onsubmit="return confirm('Sei sicuro di voler disattivare questo prodotto?');"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center hover:text-red-600">
                                                            <i class="fas fa-trash-alt mr-1"></i> Disattiva
                                                        </button>
                                                    </form>
                                                @endunless
                                            @endif

                                            {{-- Ripristina (solo se soft-deleted) --}}
                                            @if($product->trashed() && auth()->user()->can('products.update'))
                                                <form
                                                    action="{{ route('products.restore', $product->id) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Ripristinare questo prodotto?');"
                                                >
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center hover:text-green-600">
                                                        <i class="fas fa-undo mr-1"></i> Ripristina
                                                    </button>
                                                </form>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                            @if($products->isEmpty())
                                <tr>
                                    <td :colspan="extended ? 5 : 3" x-cloak class="px-6 py-4 text-center text-gray-500">
                                        Nessun prodotto trovato.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                {{-- Paginazione --}}
                <div class="mt-4 px-6 py-2">
                    {{ $products->links('vendor.pagination.tailwind-compact') }}
                </div>
            </div>
        </div>
    </div>

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('productCrud', () => ({
                /* ============================================================
                * Sezione prodotti (già esistente)
                * ========================================================== */
                componentsList: @json($components),
                generateCodeUrl: @json(route('products.generate-code')),

                // Modale prodotto
                showModal: false,
                mode: 'create',
                form: {
                    id: null,
                    sku: '',
                    name: '',
                    description: '',
                    price: '',
                    components: [],
                    fabric_required_meters: 0,
                    is_active: true,
                },
                errors: {},

                // Riga espansa / colonne
                openId: null,
                extended: false,

                // Modale "Visualizza" prodotto
                showViewModal: false,
                viewProduct: null,

                openShow(product) {
                    this.viewProduct = product;
                    this.$nextTick(() => { this.showViewModal = true; });
                },

                openCreate() {
                    this.resetForm();
                    this.mode = 'create';
                    this.showModal = true;
                },

                openEdit(product) {
                    // Fallback: se per qualsiasi motivo l'accessor non è presente,
                    // prendo la quantity della riga di pivot con variable_slot='TESSU'.
                    const fabricFromPivot = (product.components || [])
                        .find(c => (c.pivot && c.pivot.variable_slot === 'TESSU'))
                        ?.pivot?.quantity ?? 0;

                    this.mode = 'edit';
                    this.form = {
                        id         : product.id,
                        sku        : product.sku,
                        name       : product.name,
                        description: product.description ?? '',
                        price      : product.price,
                        // Preferisci l'attributo calcolato, altrimenti usa il pivot
                        fabric_required_meters: product.fabric_required_meters ?? fabricFromPivot,
                        is_active  : product.is_active,
                        components : (product.components ?? []).map(c => ({
                            id         : c.id,
                            quantity   : c.pivot.quantity,
                            code       : c.code,
                            description: c.description,
                            unit       : c.unit, // opzionale
                            existing   : true,
                            search     : '', options: []
                        }))
                    };
                    this.errors = {};
                    this.$nextTick(() => { this.showModal = true; });
                },

                resetForm() {
                    this.form = {
                        id: null,
                        sku: '',
                        name: '',
                        description: '',
                        price: '',
                        fabric_required_meters: 0,
                        components: [],
                        is_active: true,
                    };
                    this.errors = {};
                },

                validateProduct() {
                    this.errors = {};
                    let valid = true;
                    if (!this.form.sku.trim())   { this.errors.sku  = 'Il codice è obbligatorio.'; valid = false; }
                    if (!this.form.name.trim())  { this.errors.name = 'Il nome è obbligatorio.';   valid = false; }
                    if (!this.form.price.trim()) { this.errors.price= 'Il prezzo è obbligatorio.'; valid = false; }
                    if (!this.form.fabric_required_meters) { this.errors.fabric_required_meters= 'I metri di tessuto sono obbligatori.'; valid = false; }
                    return valid;
                },

                init() {
                    @if($errors->any())
                        this.showModal = true;
                        this.mode = '{{ old('_method','create') === 'PUT' ? 'edit' : 'create' }}';
                        this.errors = @json($errors->toArray());
                        this.form = {
                            id          : {{ old('id', 'null') }},
                            sku         : '{{ old('sku', '') }}',
                            name        : '{{ old('name', '') }}',
                            description : '{{ old('description', '') }}',
                            price       : '{{ old('price', '') }}',
                            fabric_required_meters: {{ old('fabric_required_meters', 0) }},
                            components  : @json(old('components', [])),
                            is_active   : {{ old('is_active', true) ? 'true' : 'false' }},
                        };
                    @endif

                    // Inizializzo il debounce per la ricerca clienti (fallback se non usi .debounce nel template)
                    this.searchCustomersDebounced = this.debounce(this.searchCustomers, 500);
                },

                async generateCode() {
                    try {
                        const res  = await fetch(this.generateCodeUrl);
                        const json = await res.json();
                        this.form.sku = json.code;
                    } catch (e) {
                        console.error('Errore generazione codice:', e);
                    }
                },

                /* Ricerca componenti (come da tuo codice) */
                async searchComponents(idx) {
                    const term = this.form.components[idx].search?.trim() || '';
                    if (term.length < 2) {
                        this.form.components[idx].options = [];
                        return;
                    }
                    try {
                        const r = await fetch(`/components/search?q=${encodeURIComponent(term)}`, { headers:{Accept:'application/json'} });
                        this.form.components[idx].options = await r.json();
                    } catch {
                        this.form.components[idx].options = [];
                    }
                },

                selectComponent(idx, opt) {
                    Object.assign(this.form.components[idx], {
                        id         : opt.id,
                        code       : opt.code,
                        description: opt.description,
                        unit       : opt.unit_of_measure,
                        options    : [],
                    });
                },

                removeSelection(idx) {
                    Object.assign(this.form.components[idx], {
                        id: null, code: '', description: '', search: '',
                    });
                },

                addComponentRow() {
                    this.form.components.push({
                        id: null, quantity: 1,
                        search: '', options: [], existing: false
                    });
                },

                /* ============================================================
                * NUOVO: Prezzi cliente–prodotto (modali + ricerca cliente)
                * ========================================================== */

                // Modale Listino
                showPriceListModal: false,
                priceList: [],

                // Modale Cliente (create/edit on-the-fly)
                showCustomerPriceModal: false,

                // Prodotto selezionato per l'assegnazione prezzi
                selectedProduct: null,

                // Form del modale Cliente
                priceForm: {
                    mode: 'create',              // 'create' | 'edit'
                    current_id: null,            // id versione in edit
                    customer_id: null,
                    customer_name: '',
                    price: '',
                    reference_date: new Date().toISOString().slice(0,10), // default oggi
                    valid_from: '',
                    valid_to: '',
                    auto_close_prev: true,       // chiudi automaticamente la versione che copre 'valid_from'
                    notes: '',
                    // updated_at: null,         // opzionale per concorrenza ottimistica
                },
                priceErrors: {},

                // Ricerca cliente (stile customer-order-create-modal)
                customerSearch: '',              // testo input
                customerOptions: [],             // dropdown risultati
                customerLoading: false,
                searchCustomersDebounced: null,  // fallback se non usi @input.debounce nel Blade
                selectedCustomer: null,     // oggetto cliente scelto (company, email, vat_number, etc.)
                showGuestButton: false,     // di default off: non ha senso assegnare prezzi ad un guest

                // Debounce utility
                debounce(fn, wait = 300) {
                    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), wait); };
                },

                /* ---------- Apertura / chiusura modali prezzi ---------- */
                openPriceList(product) {
                    this.selectedProduct = product;
                    this.fetchPriceList().then(() => { this.showPriceListModal = true; });
                },

                openPriceCreate(product) {
                    this.selectedProduct = product;
                    this.resetCustomerPriceForm();

                    // reset ricerca + cliente selezionato
                    this.selectedCustomer = null;
                    this.customerSearch   = '';
                    this.customerOptions  = [];
                    this.customerLoading  = false;

                    this.showCustomerPriceModal = true;
                },

                closeCustomerPriceModal() {
                    this.showCustomerPriceModal = false;
                    this.resetCustomerPriceForm();

                    // reset ricerca + cliente selezionato
                    this.selectedCustomer = null;
                    this.customerSearch   = '';
                    this.customerOptions  = [];
                    this.customerLoading  = false;
                },

                /* ----------------- API prezzi ----------------- */
                async fetchPriceList() {
                    if (!this.selectedProduct) return;
                    const url = `/products/${this.selectedProduct.id}/customer-prices`;
                    const r = await fetch(url, { headers: {Accept: 'application/json'} });
                    const j = await r.json();
                    this.priceList = j.data ?? [];
                },

                /* ---------------- Ricerca clienti ----------------
                * identica nello spirito a customer-order-create-modal:
                * - input: customerSearch
                * - debounce: @input.debounce.500 (o this.searchCustomersDebounced)
                * - risultati: customerOptions
                * - key: option.id + '-' + idx
                */
                async searchCustomers() {
                    const term = (this.customerSearch || '').trim();
                    if (term.length < 2) { this.customerOptions = []; return; }

                    this.customerLoading = true;
                    try {
                        const r = await fetch(`/customers/search?q=${encodeURIComponent(term)}`, {
                            headers: { Accept:'application/json' }, credentials:'same-origin'
                        });
                        if (!r.ok) throw new Error(r.status);

                        const data = await r.json();
                        const arr  = Array.isArray(data) ? data : [];

                        // Deduplica: privilegio id, altrimenti stringa serializzata
                        const seen = new Set();
                        this.customerOptions = arr.filter((option) => {
                            const key = option && option.id != null ? `id:${option.id}` : `str:${JSON.stringify(option)}`;
                            if (seen.has(key)) return false;
                            seen.add(key); return true;
                        }).slice(0, 20);

                    } catch (e) {
                        console.error('searchCustomers error', e);
                        this.customerOptions = [];
                    } finally {
                        this.customerLoading = false;
                    }
                },

                // Selezione dal dropdown: popolo il form + risoluzione create→edit
                selectCustomer(option) {
                    // Normalizzo nome/label
                    const name = option?.company || option?.name || option?.label || '';

                    // Salvo oggetto completo per il pannellino riepilogo
                    this.selectedCustomer = {
                        id: option?.id ?? null,
                        company: option?.company ?? option?.name ?? '',
                        email: option?.email ?? null,
                        vat_number: option?.vat_number ?? null,
                        tax_code: option?.tax_code ?? null,
                        // se l’endpoint non restituisce shipping_address pronto, usa fallback base
                        shipping_address: option?.shipping_address
                            ?? [option?.address, option?.postal_code && option?.city ? `${option.postal_code} ${option.city}` : option?.city, option?.province, option?.country]
                                .filter(Boolean).join(', ')
                    };

                    // Allineo il form tecnico
                    this.priceForm.customer_id   = this.selectedCustomer.id;
                    this.priceForm.customer_name = name; // compat, se lo usi altrove

                    // UI input + dropdown
                    this.customerSearch  = name;
                    this.customerOptions = [];

                    // Escamotage: se esiste una versione valida alla data, passo in edit e precompilo
                    this.resolveCustomerPrice();
                },

                // Risolve product+customer(+date) → versione valida o ultimo storico
                async resolveCustomerPrice() {
                    this.priceErrors = {};
                    if (!this.selectedProduct || !this.priceForm.customer_id) return;

                    const url = `/products/${this.selectedProduct.id}/customer-prices/resolve?customer_id=${this.priceForm.customer_id}&date=${this.priceForm.reference_date}`;
                    const r   = await fetch(url, { headers: {Accept:'application/json'} });
                    const j   = await r.json();

                    if (j.current) {
                        this.priceForm.mode       = 'edit';
                        this.priceForm.current_id = j.current.id;
                        this.priceForm.price      = String(j.current.price);
                        this.priceForm.valid_from = j.current.valid_from ?? '';
                        this.priceForm.valid_to   = j.current.valid_to ?? '';
                        this.priceForm.notes      = j.current.notes ?? '';
                        // this.priceForm.updated_at = j.current.updated_at ?? null;  // se vuoi concorrenza
                    } else {
                        this.priceForm.mode       = 'create';
                        this.priceForm.current_id = null;
                        if (j.latestArchived) {
                            this.priceForm.price      = String(j.latestArchived.price);
                            this.priceForm.valid_from = '';
                            this.priceForm.valid_to   = '';
                            this.priceForm.notes      = j.latestArchived.notes ?? '';
                        } else {
                            this.priceForm.price = '';
                            this.priceForm.valid_from = '';
                            this.priceForm.valid_to   = '';
                            this.priceForm.notes      = '';
                        }
                    }
                },

                onValidFromChanged() {
                    // La chiusura della versione precedente avviene server-side
                },

                normalizePrice(v) {
                    return (typeof v === 'string') ? v.replace(',', '.') : v;
                },

                // Crea/Aggiorna versione prezzo
                async saveCustomerPrice() {
                    try {
                        this.priceErrors = {};

                        const payload = {
                            customer_id    : this.priceForm.customer_id,
                            price          : this.normalizePrice(this.priceForm.price),
                            currency       : 'EUR',
                            valid_from     : this.priceForm.valid_from || null,
                            valid_to       : this.priceForm.valid_to   || null,
                            notes          : this.priceForm.notes      || null,
                            auto_close_prev: this.priceForm.mode === 'create' ? !!this.priceForm.auto_close_prev : false,
                            updated_at     : this.priceForm.mode === 'edit' ? (this.priceForm.updated_at ?? null) : null,
                        };

                        let url = `/products/${this.selectedProduct.id}/customer-prices`;
                        let method = 'POST';
                        if (this.priceForm.mode === 'edit' && this.priceForm.current_id) {
                            url    = `/products/${this.selectedProduct.id}/customer-prices/${this.priceForm.current_id}`;
                            method = 'PUT';
                        }

                        // 🔐 Token CSRF dal meta
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                        const r = await fetch(url, {
                            method,
                            headers: {
                                'Content-Type'   : 'application/json',
                                'Accept'         : 'application/json',
                                'X-CSRF-TOKEN'   : token,                    // ← obbligatorio
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin',                      // ← invia i cookie di sessione
                            body: JSON.stringify(payload)
                        });

                        if (r.status === 409) {
                            const j = await r.json();
                            alert(j.message || 'Il record è stato modificato da un altro utente.');
                            return;
                        }
                        if (r.status === 422) {
                            const j = await r.json();
                            this.priceErrors = j.errors || {};
                            alert(j.message || 'Errori di validazione.');
                            return;
                        }
                        if (!r.ok) {
                            const j = await r.json().catch(() => ({}));
                            alert(j.message || 'Errore salvataggio.');
                            return;
                        }

                        await this.fetchPriceList();
                        alert('Prezzo salvato con successo.');
                        this.closeCustomerPriceModal();
                        this.showPriceListModal = true;

                    } catch (e) {
                        console.error(e);
                        alert('Errore di rete.');
                    }
                },

                async deleteCustomerPrice(row) {
                    if (!this.selectedProduct) return;
                    if (!confirm('Eliminare questa versione di prezzo?')) return;

                    try {
                        const url = `/products/${this.selectedProduct.id}/customer-prices/${row.id}`;
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                        const r = await fetch(url, {
                            method: 'DELETE',
                            headers: {
                                'Accept'          : 'application/json',
                                'X-CSRF-TOKEN'    : token,                    // ← obbligatorio
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'                       // ← invia i cookie di sessione
                        });

                        if (!r.ok) {
                            const j = await r.json().catch(() => ({}));
                            alert(j.message || 'Errore eliminazione.');
                            return;
                        }
                        await this.fetchPriceList();

                    } catch (e) {
                        console.error(e);
                        alert('Errore di rete.');
                    }
                },

                resetCustomerPriceForm() {
                    this.priceForm = {
                        mode: 'create',
                        current_id: null,
                        customer_id: null,
                        customer_name: '',
                        price: '',
                        reference_date: new Date().toISOString().slice(0,10),
                        valid_from: '',
                        valid_to: '',
                        auto_close_prev: true,
                        notes: '',
                    };
                    this.priceErrors      = {};
                    this.selectedCustomer = null;  // ← reset pannellino
                    this.customerSearch   = '';    // ← reset input
                    this.customerOptions  = [];
                },

            }));
        });
    </script>
@endpush


</x-app-layout>