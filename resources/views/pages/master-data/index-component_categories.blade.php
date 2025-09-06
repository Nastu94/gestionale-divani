{{-- resources/views/pages/master-data/index-component_categories.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Categorie Componenti') }}</h2>
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
        <div x-data="categoryCrud()" class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                @if(auth()->user()->can('categories.create'))
                    {{-- Pulsante “Nuovo” --}}
                    <div class="flex justify-end m-2 p-2">
                        <button 
                            @click="openCreate"
                            class="inline-flex items-center m-2 px-3 py-1.5 bg-purple-600 rounded-md
                                        text-xs font-semibold text-white uppercase hover:bg-purple-500
                                        focus:outline-none focus:ring-2 focus:ring-purple-300 transition"
                        >
                            <i class="fas fa-plus mr-1"></i> Nuovo
                        </button>
                    </div>
                @endif

                {{-- Modale Create / Edit --}}
                <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black opacity-75"></div>
                    <div class="relative z-10 w-full max-w-3xl">
                        <x-component_categories-create-modal 
                            :categories="$categories"
                        />
                    </div>
                </div>

                @php
                    /* value => label  */
                    $phaseOptions = collect(\App\Enums\ProductionPhase::cases())
                        ->mapWithKeys(fn ($p) => [$p->value => $p->label()]);
                @endphp

                {{-- Tabella espandibile --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700">
                            <tr class="uppercase tracking-wider">
                                <th class="px-6 py-2 text-left whitespace-nowrap">#</th>
                                <x-th-menu
                                    field="code"
                                    label="Codice"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="categories.index"
                                />
                                <x-th-menu
                                    field="name"
                                    label="Nome"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="categories.index"
                                />
                                <th class="px-6 py-2 text-left whitespace-nowrap">Descrizione</th>
                                <x-th-menu
                                    field="phase"
                                    label="Fase di Produzione"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    reset-route="categories.index"
                                    :options="$phaseOptions"   {{-- ★ qui l’array --}}
                                />
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($categories as $category)
                                @php
                                    $canEdit   = auth()->user()->can('categories.update');
                                    $canDelete = auth()->user()->can('categories.delete');
                                    $canCrud   = $canEdit || $canDelete;
                                @endphp

                                {{-- Riga principale --}}
                                <tr
                                    @if($canCrud)
                                        @click="openId = (openId === {{ $category->id }} ? null : {{ $category->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $category->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $loop->iteration + ($categories->currentPage()-1)*$categories->perPage() }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $category->code }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $category->name }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $category->description }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ $category->phasesEnum()->map->label()->join(', ') }}
                                    </td>
                                </tr>

                                {{-- Riga espansa con Modifica / Elimina--}}
                                @if($canCrud)
                                <tr x-show="openId === {{ $category->id }}" x-cloak>
                                    <td
                                        :colspan="extended ? 5 : 5"
                                        class="px-6 py-3 bg-gray-200 dark:bg-gray-700"
                                    >
                                        <div class="flex items-center space-x-4 text-xs">
                                            @if($canEdit)
                                                @php
                                                    $catJson = [
                                                        'id'          => $category->id,
                                                        'code'        => $category->code,
                                                        'name'        => $category->name,
                                                        'description' => $category->description,
                                                        'phases'      => $category->phaseLinks->pluck('phase')->values()->all(),
                                                    ];
                                                @endphp

                                                <button  type="button"
                                                        @click.stop="openEdit(@js($catJson))"
                                                        class="inline-flex items-center hover:text-yellow-600">
                                                    <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                                </button>
                                            @endif

                                            @if($canDelete)
                                                <form
                                                    action="{{ route('categories.destroy', $category) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Sei sicuro di voler eliminare questa categoria?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center hover:text-red-600">
                                                        <i class="fas fa-trash-alt mr-1"></i> Elimina
                                                    </button>
                                                </form>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                            @if($categories->isEmpty())
                                <tr>
                                    <td colspan="5" x-cloak class="px-6 py-4 text-center text-gray-500">
                                        Nessuna categoria trovata.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                {{-- Paginazione --}}
                <div class="mt-4 px-6 py-2">
                    {{ $categories->links('vendor.pagination.tailwind-compact') }}
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('categoryCrud', () => ({
                    // Modal
                    showModal: false,
                    mode: 'create',
                    form: { 
                        id: null,
                        code: '',
                        name: '',
                        description: '',
                        phases: [],
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

                    openEdit(category) {
                        console.log(category);
                        this.mode = 'edit';
                        this.form = {
                            id:          category.id,
                            code:        category.code,
                            name:        category.name,
                            description: category.description,
                            phases:      category.phases ?? [],   // ← arriva già array di int
                        };
                        this.errors = {};
                        this.showModal = true;
                    },

                    resetForm() {
                        this.form = { 
                            id: null, 
                            code: '',
                            name: '',
                            description: '',
                            phases: [],
                        };
                        this.errors = {};
                    },

                    validateCategory() {
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
                        if (! this.form.name.trim()) {
                            this.errors.name = 'Il nome è obbligatorio.';
                            valid = false;
                        }
                        if (this.form.phases.length === 0) {
                            this.errors.phases = 'Almeno una fase è richiesta.';
                            valid = false;
                        }
                        return valid;
                    },

                    init() {
                        @if($errors->any())
                            this.showModal = true;
                            this.mode = {!! json_encode(old('_method','create') === 'PUT' ? 'edit' : 'create') !!};

                            // array di errori di Laravel: { field: [msg, ...], ... }
                            this.errors = {!! json_encode($errors->toArray(), JSON_UNESCAPED_UNICODE) !!};

                            // id numerico o null, MAI stringa vuota → evita "id: ,"
                            this.form = {
                                id: {!! old('id') !== null && old('id') !== '' ? (int) old('id') : 'null' !!},
                                code: {!! json_encode(old('code','')) !!},
                                name: {!! json_encode(old('name','')) !!},
                                description: {!! json_encode(old('description','')) !!},
                                phases: {!! json_encode(old('phases', [])) !!},
                            };
                        @endif
                    },

                }));
            });
        </script>
    @endpush

</x-app-layout>