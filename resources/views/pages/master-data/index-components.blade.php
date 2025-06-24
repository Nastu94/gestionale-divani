{{-- resources/views/pages/master-data/index-component.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Componenti') }}</h2>
            <x-dashboard-tiles />
        </div>
    </x-slot>

    <div class="py-6">
        <div x-data="componentCrud()" class="max-w-full mx-auto sm:px-6 lg:px-8">
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
                        <x-component-create-modal :components="$components" />
                    </div>
                </div>             

                {{-- Tabella espandibile --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700">
                            <tr class="uppercase tracking-wider">
                                <th class="px-6 py-2 text-left">Codice</th>
                                <th class="px-6 py-2 text-left">Descrizione</th>

                                
                                {{-- Colonne indirizzi, visibili solo se extended --}}
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Materiale</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Altezza</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Larghezza</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Lunghezza</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Peso</th>

                                <th class="px-6 py-2 text-left whitespace-nowrap">Unità di misura</th>
                                <th class="px-6 py-2 text-center">Attivo</th>

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
                                    @if($canCrud)
                                        @click="openId = (openId === {{ $component->id }} ? null : {{ $component->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $component->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $customer->code }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $component->description }}</td>

                                    {{-- Colonne indirizzi, visibili solo se extended --}}
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->material ?? '—' }}</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->height ?? '—' }}</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->width ?? '—' }}</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->length ?? '—' }}</td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $component->weight ?? '—' }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $component->unit_of_measure}}</td>
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

                                {{-- Riga espansa con Modifica / Elimina / Estendi --}}
                                @if($canCrud)
                                <tr x-show="openId === {{ $component->id }}" x-cloak>
                                    <td
                                        :colspan="extended ? 11 : 8"
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

                                            @if($canDelete)
                                                <form
                                                    action="{{ route('components.destroy', $component) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Sei sicuro di voler eliminare questo componente?');"
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
                        </tbody>
                    </table>
                </div>

                {{-- Paginazione --}}
                <div class="mt-4 px-6">
                    {{ $components->links() }}
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('componentCrud', () => ({
                    // Modal
                    showModal: false,
                    mode: 'create',
                    form: { 
                        id: null, 
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

                    // Per riga espansa e colonne aggiuntive
                    openId: null,
                    extended: false,

                    openCreate() {
                        this.resetForm();
                        this.mode = 'create';
                        this.showModal = true;
                    },

                    openEdit(component) {
                        this.mode = 'edit';
                        this.form.id               = component.id;
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

                    resetForm() {
                        this.form = { 
                            id: null, 
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

                    validatecomponent() {
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
                }));
            });
        </script>
    @endpush

</x-app-layout>