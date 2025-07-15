{{-- resources/views/pages/master-data/index-component.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Componenti') }}</h2>
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
        <div x-data="componentCrud()" class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                {{-- Pulsante “Nuovo” --}}
                <div class="flex justify-end m-2 p-2">
                    @if(auth()->user()->can('components.create'))
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
                    <div class="absolute inset-0 bg-black opacity-75" @click="showModal = false"></div>
                    <div class="relative z-10 w-full max-w-3xl">
                        <x-component-create-modal 
                            :components="$components"
                            :categories='$categories' 
                        />
                    </div>
                </div>
                
                {{-- Modale Fornitori --}}
                <div x-show="showSupplierModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black opacity-75" @click="showSupplierModal = false"></div>
                    <div class="relative z-10 w-full max-w-xl">
                        <x-component-supplier-modal 
                            :suppliers="$suppliers"
                        />
                    </div>
                </div>

                {{-- Modale Listino Prezzi --}}
                <div x-show="showPriceListModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black opacity-75" @click="showPriceListModal = false"></div>
                    <div class="relative z-10 w-full max-w-2xl">
                        <x-component-price-list-modal/>
                    </div>
                </div>

                {{-- Modale Giacenze --}}
                <div x-show="showStockModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black opacity-75" @click="showStockModal = false"></div>
                    <div class="relative z-10 w-full max-w-2xl">
                        <x-stock-levels-modal/>
                    </div>
                </div>

                {{-- Tabella espandibile --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700">
                            <tr class="uppercase tracking-wider">
                                <x-th-menu
                                    field="code"
                                    label="Codice"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="components.index"
                                />
                                <x-th-menu
                                    field="description"
                                    label="Descrizione"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="components.index"
                                />
                                
                                {{-- Colonne indirizzi, visibili solo se extended --}}
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Materiale</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Altezza</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Larghezza</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Lunghezza</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Peso</th>

                                <th class="px-6 py-2 text-left whitespace-nowrap">Unità di misura</th>
                                <x-th-menu
                                    field="category"
                                    label="Categoria"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="components.index"
                                />
                                <x-th-menu
                                    field="is_active"
                                    label="Attivo"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    align="right"
                                    :filterable="false"
                                    reset-route="components.index"
                                />

                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($components as $component)
                                @php
                                    $canEdit   = auth()->user()->can('components.update');
                                    $canDelete = auth()->user()->can('components.delete');
                                    $canCrud   = $canEdit || $canDelete;
                                @endphp

                                {{-- Riga principale --}}
                                <tr
                                    @if($canCrud || (auth()->user()->can('price_lists.view') || auth()->user()->can('price_lists.create')))
                                        @click="openId = (openId === {{ $component->id }} ? null : {{ $component->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $component->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $component->code }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $component->description }}</td>

                                    {{-- Colonne indirizzi, visibili solo se extended --}}
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->material ?? '—' }}</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->height ?? '—' }} @if($component->height)cm @endif</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->width ?? '—' }} @if($component->width)cm @endif</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->length ?? '—' }} @if($component->length)cm @endif</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->weight ?? '—' }} @if($component->weight)kg @endif</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $component->unit_of_measure}}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $component->category->name}}</td>
                                    <td class="px-6 py-2 text-center whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                            :class="{
                                                'bg-green-100 text-green-800': {{ $component->is_active ? 'true' : 'false' }},
                                                'bg-red-100 text-red-800': {{ $component->is_active ? 'false' : 'true' }}
                                            }"
                                        >
                                            {{ $component->is_active ? 'Sì' : 'No' }}
                                        </span>
                                    </td>
                                </tr>

                                {{-- Riga espansa con Modifica / Elimina / Ripristina --}}
                                @if($canCrud || (auth()->user()->can('price_lists.view') || auth()->user()->can('price_lists.create')))
                                <tr x-show="openId === {{ $component->id }}" x-cloak>
                                    <td
                                        :colspan="extended ? 10 : 5"
                                        class="px-6 py-3 bg-gray-200 dark:bg-gray-700"
                                    >
                                        <div class="flex items-center space-x-4 text-xs">
                                            @if($canEdit)
                                                <button
                                                    type="button"
                                                    @click='openEdit(@json($component))'
                                                    class="inline-flex items-center hover:text-yellow-600"
                                                >
                                                    <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                                </button>
                                            @endif

                                            @can('price_lists.view')
                                                <button
                                                    type="button"
                                                    @click="openPriceListModal({{ $component->id }})"
                                                    class="inline-flex items-center hover:text-purple-600"
                                                >
                                                    <i class="fas fa-list mr-1"></i> Listini
                                                </button>
                                            @endcan

                                            @can('price_lists.create')
                                                <button
                                                    type="button"
                                                    @click='openSupplierModal(@json($component))'
                                                    class="inline-flex items-center hover:text-blue-600"
                                                >
                                                    <i class="fas fa-handshake mr-1"></i> Fornitori
                                                </button>
                                            @endcan

                                            @php
                                                /* esiste almeno una giacenza positiva? */
                                                $hasPositiveStock = $component->stockLevels->isNotEmpty();
                                            @endphp

                                            @if($hasPositiveStock && auth()->user()->can('stock.view'))
                                                <button
                                                    type="button"
                                                    @click="openStockModal({{ $component->id }})"
                                                    class="inline-flex items-center hover:text-teal-600"
                                                >
                                                    <i class="fas fa-warehouse mr-1"></i> Giacenza
                                                </button>
                                            @endif

                                            @if($canDelete && $component->is_active)
                                                {{-- Elimina (solo se attivo) --}}
                                                @unless($component->trashed())
                                                    {{-- Disattiva (solo se non soft-deleted) --}}
                                                    <button
                                                        type="button"
                                                        @click="deleteComponent({{ $component->id }})"
                                                        class="inline-flex items-center hover:text-red-600"
                                                    >
                                                        <i class="fas fa-trash-alt mr-1"></i> Disattiva
                                                    </button>
                                                @endunless
                                            @endif
                                        
                                            {{-- Ripristina (solo se soft-deleted) --}}
                                            @if($component->trashed() && auth()->user()->can('components.update') && $component->is_active === false)
                                                {{-- Form per ripristino --}}
                                                <form
                                                    action="{{ route('components.restore', $component->id) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Ripristinare questo componente?');"
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
                        </tbody>
                    </table>
                </div>

                {{-- Paginazione --}}
                <div class="mt-4 px-6 py-2">
                    {{ $components->links('vendor.pagination.tailwind-compact') }}
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('componentCrud', () => ({

                    /**
                     * Variabili per la gestione della tabella
                     */
                    openId: null,
                    extended: false,

                    /**
                     * Modale per la gestione dei componenti
                     * 
                     */
                    showModal: false,
                    mode: 'create',

                    /**
                     * Form per Create/Edit Componenti
                     */
                    form: { 
                        id: null,
                        category_id: null,
                        code: '',
                        description: '',
                        material: '',
                        height: null,
                        width: null,
                        length: null,
                        weight: null,
                        unit_of_measure: '',
                        is_active: true, 
                    },
                    errors: {},

                    /**
                     * Apre il modale per la creazione di un nuovo componente
                     */
                    openCreate() {
                        this.resetForm();
                        this.mode = 'create';
                        this.showModal = true;
                    },
                    
                    /**
                     * Apre il modale per la modifica di un componente esistente
                     * 
                     * @param {Object} component - Il componente da modificare
                     */
                    openEdit(component) {
                        this.mode = 'edit';
                        this.form.id               = component.id;
                        this.form.category_id      = component.category_id;
                        this.form.code             = component.code;
                        this.form.description      = component.description;
                        this.form.material         = component.material   ?? '';
                        this.form.length           = component.length      ?? '';
                        this.form.width            = component.width      ?? '';
                        this.form.height           = component.height      ?? '';
                        this.form.weight           = component.weight      ?? '';
                        this.form.unit_of_measure  = component.unit_of_measure;
                        this.form.is_active        = component.is_active;
                        this.errors = {};
                        this.showModal = true;
                    },

                    /**
                     * Reset del form per Create/Edit Componenti
                     */
                    resetForm() {
                        this.form = { 
                            id: null, 
                            category_id: null,
                            code: '',
                            description: '',
                            material: null,
                            height: null,
                            width: null,
                            length: null,
                            weight: null,
                            unit_of_measure: '',
                            is_active: true,
                        };
                        this.errors = {};
                    },

                    /**
                     * Validazione del componente (Create o Edit)
                     */
                    validateComponent() {
                        this.errors = {};
                        let valid = true;
                        if (! this.form.code.trim()) {
                            this.errors.code = 'Il codice è obbligatorio.';
                            valid = false;
                        }
                        if (! this.form.description.trim()) {
                            this.errors.description = 'La descrizione è obbligatorio.';
                            valid = false;
                        }
                        if (! this.form.unit_of_measure.trim()) {
                            this.errors.unit_of_measure = 'L\'unità di misura è obbligatoria.';
                            valid = false;
                        }
                        return valid;
                    },

                    /**
                     * Genera un codice per il componente basato sulla categoria
                     */
                    generateCode() {
                        if (! this.form.category_id) return;

                        fetch(`/components/generate-code?category_id=${this.form.category_id}`)
                            .then(r => r.json())
                            .then(data => this.form.code = data.code)
                            .catch(() => alert('Impossibile generare il codice, riprova.'));
                    },

                    /**
                     * Inizializza i modali se ci sono errori
                     */
                    init () {
                        /* (A) errori del form componente */
                        @if ($errors->any() && ! session('supplier_modal'))
                            this.showModal = true
                            this.mode      = '{{ old('_method', 'create') === 'PUT' ? 'edit' : 'create' }}'
                            this.errors    = @json($errors->toArray())
                            this.form      = {
                                id              : {{ old('id', 'null') }},
                                category_id     : {{ old('category_id', 'null') }},
                                code            : '{{ old('code', '') }}',
                                description     : '{{ old('description', '') }}',
                                material        : '{{ old('material', '') }}',
                                height          : {{ old('height', 'null') }},
                                width           : {{ old('width', 'null') }},
                                length          : {{ old('length', 'null') }},
                                weight          : {{ old('weight', 'null') }},
                                unit_of_measure : '{{ old('unit_of_measure', '') }}',
                                is_active       : {{ old('is_active', 'true') ? 'true' : 'false' }},
                            }
                        @endif

                        /* (B) errori modale fornitori */
                        @if (session('supplier_modal'))
                            this.showSupplierModal = true

                            this.$nextTick(() => {
                                Alpine.deferMutations(() => {
                                    this.$dispatch('prefill-supplier-form', {
                                        component_id : {{ session('supplier_component') ?? 'null' }},
                                        supplier_id  : '{{ old('supplier_id') }}',
                                        price        : '{{ old('price') }}',
                                        lead_time    : '{{ old('lead_time') }}',
                                    })
                                })
                            })
                        @endif
                    },

                    /**
                     * Mostra il modale per abbinare un componente a un fornitore
                     */
                    showSupplierModal : false,

                    /**
                     * Apre il modale per la lista dei fornitori del componente
                     * 
                     * @param {Object|number} componentOrId - Il componente o l'ID del componente
                     */
                    openPriceListModal(componentOrId) {
                        // accetta sia l’oggetto che un semplice id
                        const id = (typeof componentOrId === 'object')
                            ? componentOrId.id
                            : componentOrId;

                        this.$dispatch('load-price-list', { component_id: id });

                        this.$nextTick(() => { this.showPriceListModal = true });
                    },

                    /**
                     * Apre il modale per aggiungere o modificare un fornitore per il componente
                     * 
                     * @param {Object} component - Il componente per cui aggiungere il fornitore
                     */
                    openSupplierModal(component) {
                        /* invia i dati al modale tramite evento globale */
                        this.$dispatch('prefill-supplier-form', {
                            component_id : component.id,
                            supplier_id  : '',
                            price        : '',
                            lead_time    : ''
                        })

                        /* mostra il modale nel prossimo tick */
                        this.$nextTick(() => { this.showSupplierModal = true })
                    },

                    /**
                     * Mostra il modale per gestire i listini prezzi del componente
                     */
                    showPriceListModal : false,

                    /**
                     * Elimina un componente
                     * 
                     * @param {number} id - ID del componente da eliminare
                     * @returns {void}
                     */
                    deleteComponent(id) {
                        if (! confirm('Disattivare questo componente?')) return;

                        fetch(`/components/${id}`, {
                            method : 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept'      : 'application/json'
                            }
                        })
                        .then(async res => {
                            if (res.status === 409) {
                                // blocco protetto dal controller
                                const data = await res.json();
                                alert(data.message);          
                                return;
                            }

                            if (res.ok) {
                                // eliminazione riuscita, ricarico la pagina per aggiornare la lista
                                location.reload();
                                return;
                            }

                            // qualunque altro errore
                            alert('Errore imprevisto, riprovare.');
                        })
                        .catch(() => alert('Errore di rete, controlla la connessione.'));
                    },

                    /* ----------  SEZIONE GIACENZE  -------------------- */
                    showStockModal : false,
                    stockRows      : [],
                    stockModalTitle: '',

                    openStockModal(componentId) {
                        this.stockRows       = []
                        this.stockModalTitle = ''

                        fetch(`/components/${componentId}/stock`, {
                            headers: {
                                'Accept'             : 'application/json',
                                'X-Requested-With'   : 'XMLHttpRequest',   // opzionale, ma aiuta
                            }
                        })
                        .then(async res => {
                            // se non è JSON evito di chiamare res.json()
                            const isJson = res.headers.get('content-type')?.includes('application/json')

                            if (!isJson) {
                                const text = await res.text()   // HTML o altro
                                console.error('Risposta non JSON:', text)
                                alert('Risposta non valida dal server.')
                                return
                            }

                            const data = await res.json()

                            if (!res.ok) {                    // 4xx / 5xx
                                alert(data.message ?? 'Errore sul server.')
                                return
                            }

                            // ok 2xx
                            this.stockRows       = data.rows
                            this.stockModalTitle = data.rows[0]?.component_code ?? ''
                            this.showStockModal  = true
                        })
                        .catch(() => {
                            alert('Errore di rete, riprovare.')
                        })
                    },

                }));
            });
        </script>
    @endpush

</x-app-layout>