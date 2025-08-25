{{-- resources/views/pages/master-data/index-suppliers.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Fornitori') }}</h2>
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
        <div x-data="supplierCrud()" x-init="init()" class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                {{-- Pulsante “Nuovo” --}}
                <div class="flex justify-end m-2 p-2">
                    @if(auth()->user()->can('suppliers.create'))
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
                        <x-supplier-create-modal :suppliers="$suppliers" />
                    </div>
                </div>

                {{-- Modale Listini componenti --}}
                <div x-show="showComponentPriceListModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    {{-- overlay scuro chiusura --}}
                    <div class="absolute inset-0 bg-black opacity-75"
                        @click="showComponentPriceListModal = false; rows = []"></div>

                    <div class="relative z-10 w-full max-w-4xl">
                        <x-supplier-component-price-list-modal />
                    </div>
                </div>

                {{-- Modale Aggiungi Componenti --}}
                <div x-show="showAddComponentModal" x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black opacity-75"></div>
                    <div class="relative z-10">
                        <x-supplier-add-components-modal :components="$components"/>
                    </div>
                </div>


                {{-- Tabella espandibile --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700">
                            <tr class="uppercase tracking-wider">
                                <th class="px-6 py-2 text-left">#</th>
                                <x-th-menu
                                    field="name"
                                    label="Società"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :filterable="true"
                                    reset-route="suppliers.index"
                                />
                                <th class="px-6 py-2 text-left">P.IVA</th>
                                <th class="px-6 py-2 text-left">CF</th>
                                <th class="px-6 py-2 text-left">Email</th>
                                <th class="px-6 py-2 text-left">Telefono</th>

                                {{-- Colonne indirizzi, visibili solo se extended --}}
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Sito Web</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Termini di Pagamento</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Indirizzo Fornitore</th>

                                <x-th-menu
                                    field="is_active"
                                    label="Attivo"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="suppliers.index"
                                    align="right"
                                    :filterable="false"
                                />
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($suppliers as $supplier)
                                @php
                                    $canEdit   = auth()->user()->can('suppliers.update');
                                    $canDelete = auth()->user()->can('suppliers.delete');
                                    $canCrud   = $canEdit || $canDelete;

                                    $website = $supplier->website ?? '';
                                    $paymentTerms = $supplier->payment_terms ?? '';
                                    $addr         = $supplier->address ?? [];
                                    $via          = $addr['via']         ?? null;
                                    $city         = $addr['city']        ?? null;
                                    $postal_code  = $addr['postal_code'] ?? null;
                                    $country      = $addr['country']     ?? null;
                                @endphp

                                {{-- Riga principale --}}
                                <tr
                                    @if($canCrud || (auth()->user()->can('price_lists.view') || auth()->user()->can('price_lists.create')))
                                        @click="openId = (openId === {{ $supplier->id }} ? null : {{ $supplier->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $supplier->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $loop->iteration + ($suppliers->currentPage()-1)*$suppliers->perPage() }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $supplier->name }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $supplier->vat_number ?? '—' }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $supplier->tax_code ?? '—' }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $supplier->email ?? '—' }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $supplier->phone ?? '—' }}</td>

                                    {{-- Colonne indirizzi, visibili solo se extended --}}
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $website ?: '—' }}</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $paymentTerms ?: '—' }}</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">
                                        @if($via || $city || $postal_code || $country)
                                            {{-- Mostro solo i pezzi definiti, separati da virgola --}}
                                            {{ $via }}@if($via && $postal_code), @endif
                                            {{ $postal_code }}@if(($via||$city||$postal_code) && $country), @endif
                                            {{ $city }}@if(($postal_code||$city) && $country), @endif
                                            {{ $country }}
                                        @else
                                            —  
                                        @endif
                                    </td>

                                    <td class="px-6 py-2 text-center whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                            :class="{
                                                'bg-green-100 text-green-800': {{ $supplier->is_active ? 'true' : 'false' }},
                                                'bg-red-100 text-red-800': {{ $supplier->is_active ? 'false' : 'true' }}
                                            }"
                                        >
                                            {{ $supplier->is_active ? 'Sì' : 'No' }}
                                        </span>
                                    </td>
                                </tr>

                                {{-- Riga espansa con Modifica / Elimina / Ripristina --}}
                                @if($canCrud || (auth()->user()->can('price_lists.view') || auth()->user()->can('price_lists.create')))
                                <tr x-show="openId === {{ $supplier->id }}" x-cloak>
                                    <td
                                        :colspan="extended ? 10 : 7"
                                        class="px-6 py-3 bg-gray-200 dark:bg-gray-700"
                                    >
                                        <div class="flex items-center space-x-4 text-xs">
                                            @if($canEdit)
                                                <button
                                                    type="button"
                                                    @click='openEdit(@json($supplier))'
                                                    class="inline-flex items-center hover:text-yellow-600"
                                                >
                                                    <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                                </button>
                                            @endif

                                            @can('price_lists.view')
                                                <button
                                                    type="button"
                                                    @click="openComponentListModal({{ $supplier->id }})"
                                                    class="inline-flex items-center hover:text-blue-600">
                                                    <i class="fas fa-list mr-1"></i> Listino
                                                </button>
                                            @endcan

                                            @can('price_lists.create')
                                                <button
                                                    type="button"
                                                    @click.stop="openAddComponentModal({{ $supplier->id }})"
                                                    class="inline-flex items-center hover:text-emerald-600"
                                                    title="Aggiungi componenti a listino"
                                                >
                                                    <i class="fas fa-plus-square mr-1"></i> Componenti
                                                </button>
                                            @endcan

                                            @if($canDelete)
                                                @if(!$supplier->trashed())
                                                    {{-- Elimina (solo se non soft-deleted) --}}
                                                <form
                                                    action="{{ route('suppliers.destroy', $supplier) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Sei sicuro di voler disattivare questo fornitore?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center hover:text-red-600">
                                                        <i class="fas fa-trash-alt mr-1"></i> Disattiva
                                                    </button>
                                                </form>
                                                @endif
                                            @endif
                                        
                                            {{-- Ripristina (solo se soft-deleted) --}}
                                            @if($supplier->trashed() && auth()->user()->can('suppliers.update'))
                                                <form
                                                    action="{{ route('suppliers.restore', $supplier->id) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Ripristinare questo fornitore?');"
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
                    {{ $suppliers->links('vendor.pagination.tailwind-compact') }}
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('supplierCrud', () => ({
                    // Modal
                    showModal: false,
                    mode: 'create',
                    form: { 
                        id: null, 
                        name: '', 
                        vat_number: '', 
                        tax_code: '', 
                        phone: '', 
                        email: '', 
                        address: {
                            via: '',
                            city: '',
                            postal_code: '',
                            country: '',
                        },
                        website: '',
                        payment_terms: '',
                        is_active: true,
                    },
                    errors: {},

                    // Inizializza lo stato del componente
                    init() {
                        // Se la validazione di Laravel è fallita, $errors non è vuoto
                        @if($errors->any())
                            // Apri il modal
                            this.showModal = true;

                            // Seggi modalità edit se _method == PUT, altrimenti create
                            this.mode = '{{ old('_method','create') === 'PUT' ? 'edit' : 'create' }}';

                            // Popola errors con i messaggi di validazione
                            this.errors = @json($errors->toArray());

                            // Ripopola form con gli old() di Laravel
                            this.form = {
                                id:         {{ old('id', 'null') }},
                                name:       '{{ addslashes(old('name','')) }}',
                                vat_number: '{{ addslashes(old('vat_number','')) }}',
                                tax_code:   '{{ addslashes(old('tax_code','')) }}',
                                email:      '{{ addslashes(old('email','')) }}',
                                phone:      '{{ addslashes(old('phone','')) }}',
                                website:    '{{ addslashes(old('website','')) }}',
                                payment_terms: '{{ addslashes(old('payment_terms','')) }}',
                                address: {
                                    via:         '{{ addslashes(old('address.via','')) }}',
                                    city:        '{{ addslashes(old('address.city','')) }}',
                                    postal_code: '{{ addslashes(old('address.postal_code','')) }}',
                                    country:     '{{ addslashes(old('address.country','')) }}',
                                },
                                is_active:  {{ old('is_active') ? 'true' : 'false' }},
                            };
                        @endif
                    },

                    // Per riga espansa e colonne aggiuntive
                    openId: null,
                    extended: false,

                    openCreate() {
                        this.resetForm();
                        this.mode = 'create';
                        this.showModal = true;
                    },

                    openEdit(supplier) {
                        this.mode = 'edit';
                        this.form.id         = supplier.id;
                        this.form.name       = supplier.name;
                        this.form.vat_number = supplier.vat_number ?? '';
                        this.form.tax_code   = supplier.tax_code   ?? '';
                        this.form.email      = supplier.email      ?? '';
                        this.form.phone      = supplier.phone      ?? '';
                        this.form.address    = supplier.address || {
                                                    via: '',
                                                    city: '',
                                                    postal_code: '',
                                                    country: '',
                                                };
                        this.form.website    = supplier.website    ?? '';
                        this.form.payment_terms = supplier.payment_terms ?? '';
                        this.form.is_active  = supplier.is_active;
                        this.errors = {};
                        this.showModal = true;
                    },

                    resetForm() {
                        this.form = { 
                            id: null, 
                            name: '', 
                            vat_number: '', 
                            tax_code: '', 
                            phone: '', 
                            email: '', 
                            address: {
                                via: '',
                                city: '',
                                postal_code: '',
                                country: '',
                            },
                            website: '',
                            payment_terms: '',
                            is_active: true,
                        };
                        this.errors = {};
                    },

                    validateSupplier() {
                        this.errors = {};
                        let valid = true;
                        if (! this.form.name.trim()) {
                            this.errors.name = 'Il nome è obbligatorio.';
                            valid = false;
                        }
                        return valid;
                    },

                    openComponentListModal (supplierId) {
                        // accetta sia l’oggetto che un semplice id
                        const id = (typeof supplierId === 'object')
                            ? supplierId.id
                            : supplierId;

                        this.$dispatch('load-component-price-list', { supplierId: id });

                        this.$nextTick(() => { this.showComponentPriceListModal = true });
                    },

                    showComponentPriceListModal: false,

                    showAddComponentModal : false,

                    openAddComponentModal (supplierId) {
                        /* apriamo subito il modale (spinner) */
                        this.showAddComponentModal = true

                        /* evento per far conoscere l'id al modale  */
                        this.$dispatch('load-component-items', { supplier_id: supplierId })
                    },
                    
                }));
            });
        </script>
    @endpush
</x-app-layout>