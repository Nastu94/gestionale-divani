{{-- resources/views/pages/master-data/index-products.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Prodotti') }}</h2>
            <x-dashboard-tiles />
        </div>
    </x-slot>
    
    <div class="py-6">
        <div x-data="productCrud()" class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                {{-- Pulsante “Nuovo” --}}
                <div class="flex justify-end m-2 p-2">
                    <button 
                        @click="openCreate"
                        class="inline-flex items-center m-2 px-3 py-1.5 bg-purple-600 rounded-md text-xs font-semibold text-white uppercase
                            hover:bg-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-300 transition"
                    >
                        <i class="fas fa-plus mr-1"></i> Nuovo
                    </button>

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
                        <x-product-create-modal 
                            :products="$products"
                            :components="$components" 
                        />
                    </div>
                </div>             

                {{-- Tabella espandibile --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700">
                            <tr class="uppercase tracking-wider">
                                <th class="px-6 py-2 text-left">Codice Prodotto</th>
                                <th class="px-6 py-2 text-left">Nome</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Descrizione</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Prezzo</th>
                                <th class="px-6 py-2 text-center">Attivo</th>
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
                                    @if($canCrud)
                                        @click="openId = (openId === {{ $product->id }} ? null : {{ $product->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $product->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $product->sku }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $product->name }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $product->description ?? '—' }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $product->price ?? '—' }}</td>
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
                                @if($canCrud)
                                <tr x-show="openId === {{ $product->id }}" x-cloak>
                                    <td
                                        :colspan="extended ? 5 : 3"
                                        class="px-6 py-3 bg-gray-200 dark:bg-gray-700"
                                    >
                                        <div class="flex items-center space-x-4 text-xs">
                                            @if($canEdit)
                                                <button
                                                    type="button"
                                                    @click='openEdit(@json($product))'
                                                    class="inline-flex items-center hover:text-yellow-600"
                                                >
                                                    <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                                </button>
                                            @endif

                                            @if($canDelete)
                                                <form
                                                    action="{{ route('products.destroy', $product) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Sei sicuro di voler eliminare questo prodotto?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center hover:text-red-600">
                                                        <i class="fas fa-trash-alt mr-1"></i> Elimina
                                                    </button>
                                                </form>
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
            componentsList: @json($components),
            generateCodeUrl: @json(route('products.generate-code')),

            // Modal
            showModal: false,
            mode: 'create',
            form: { 
                id: null, 
                sku: '', 
                name: '', 
                description: '', 
                price: '',
                components: [],
                is_active: true, 
            },
            errors: {},

            // Per riga espansa e colonne aggiuntive
            openId: null,
            extended: false,

            openCreate() {
                this.resetForm();
                this.mode = 'create';
                this.showModal = true;
            },

            openEdit(product) {
                this.mode = 'edit';
                this.form.id            = product.id;
                this.form.sku           = product.sku;
                this.form.name          = product.name;
                this.form.description   = product.description   ?? '';
                this.form.price         = product.price;
                this.form.components    = product.components   ?? [];
                this.form.is_active     = product.is_active;
                this.errors = {};
                this.showModal = true;
            },

            resetForm() {
                this.form = { 
                    id: null, 
                    sku: '', 
                    name: '', 
                    description: '', 
                    price: '', 
                    components: [],
                    is_active: true, 
                };
                this.errors = {};
            },

            validateProduct() {
                this.errors = {};
                let valid = true;
                if (! this.form.sku.trim()) {
                    this.errors.sku = 'Il codice è obbligatorio.';
                    valid = false;
                }
                if (! this.form.name.trim()) {
                    this.errors.name = 'Il nome è obbligatorio.';
                    valid = false;
                }
                if (! this.form.price.trim()) {
                    this.errors.price = 'Il prezzo è obbligatorio.';
                    valid = false;
                }
                return valid;
            },

            init() {
                @if($errors->any())
                    this.showModal = true;
                    this.mode = '{{ old('_method','create') === 'PUT' ? 'edit' : 'create' }}';
                    this.errors = @json($errors->toArray());
                    this.form = {
                        id:         {{ old('id', 'null') }},
                        sku:           '{{ old('sku', '') }}',
                        name:          '{{ old('name', '') }}',
                        description:   '{{ old('description', '') }}',
                        price:         '{{ old('price', '') }}',
                        components:    @json(old('components', [])),
                        is_active:     {{ old('is_active', true) ? 'true' : 'false' }},
                    };
                @endif
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

        }));
    });
    </script>
    @endpush
</x-app-layout>