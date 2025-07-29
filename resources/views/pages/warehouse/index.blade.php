{{-- resources/views/pages/warehouse/index.blade.php --}}

<x-app-layout>
    {{-- ╔════════════════════════════════ HEADER ═════════════════════════════════╗ --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Magazzini') }}
            </h2>
            <x-dashboard-tiles />
        </div>

        {{-- Flash di successo / errore --}}
        @foreach (['success' => 'green', 'error' => 'red'] as $key => $color)
            @if (session($key))
                <div  x-data="{ show: true }"
                      x-init="setTimeout(() => show = false, 10000)"
                      x-show="show"
                      x-transition.opacity.duration.500ms
                      class="bg-{{ $color }}-100 border border-{{ $color }}-400 text-{{ $color }}-700 px-4 py-3 rounded relative mt-2"
                      role="alert"
                >
                    <i class="fas {{ $key === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' }} mr-2"></i>
                    <span class="block sm:inline">{{ session($key) }}</span>
                </div>
            @endif
        @endforeach
    </x-slot>
    {{-- ╚═════════════════════════════════════════════════════════════════════════╝ --}}

    <div class="py-6">
        {{-- Alpine root --}}
        <div x-data="warehouseCrud()" class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                {{-- ╔══════════════════ Pulsanti Nuovo + Estendi/Riduci ═════════════════╗ --}}
                <div class="flex justify-end m-2 p-2">
                    @can('warehouses.create')
                        <button  @click="openCreate"
                                 class="inline-flex items-center m-2 px-3 py-1.5 bg-purple-600 rounded-md
                                        text-xs font-semibold text-white uppercase hover:bg-purple-500
                                        focus:outline-none focus:ring-2 focus:ring-purple-300 transition">
                            <i class="fas fa-plus mr-1"></i> Nuovo
                        </button>
                    @endcan

                    {{-- steso pattern della vista clienti :contentReference[oaicite:0]{index=0} --}}
                    <button  type="button"
                             @click="extended = !extended"
                             class="inline-flex items-center m-2 px-3 py-1.5 bg-indigo-600 rounded-md text-xs font-semibold text-white uppercase
                                    hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-900 transition">
                        <i class="fas p-1" :class="extended ? 'fa-compress' : 'fa-expand'"></i>
                        <span x-text="extended ? 'Comprimi tabella' : 'Estendi tabella'"></span>
                    </button>
                </div>
                {{-- ╚═══════════════════════════════════════════════════════════════════╝ --}}

                {{-- ╔═════════════════════ Modale Creazione Magazzino ═══════════════════╗ --}}
                <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black opacity-75" @click="showModal = false"></div>
                    <div class="relative z-10 w-full max-w-2xl">
                        <x-warehouse-create-modal />
                    </div>
                </div>
                {{-- ╚═══════════════════════════════════════════════════════════════════╝ --}}

                {{-- ╔══════════════════════════ Tabella Magazzini ═══════════════════════╗ --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-2 text-left">#</th>
                                <th class="px-6 py-2 text-left">Codice</th>
                                <th class="px-6 py-2 text-left">Nome</th>
                                {{-- Colonna “tipo” visibile solo se extended --}}
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left">Tipo</th>
                                <th class="px-6 py-2 text-center">Attivo</th>
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($warehouses as $warehouse)
                                @php
                                    $canToggle = auth()->user()->can('warehouses.update');
                                @endphp

                                {{-- Riga principale --}}
                                <tr  @if($canToggle && $warehouse->id !== 1)
                                         @click="openId = (openId === {{ $warehouse->id }} ? null : {{ $warehouse->id }})"
                                         class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                         :class="openId === {{ $warehouse->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                     @endif
                                >
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ $loop->iteration + ($warehouses->currentPage()-1)*$warehouses->perPage() }}
                                    </td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $warehouse->code }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $warehouse->name }}</td>

                                    {{-- Colonna tipo (stock/import/...) --}}
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">{{ $warehouse->type }}</td>

                                    {{-- Badge attivo/inattivo --}}
                                    <td class="px-6 py-2 text-center whitespace-nowrap">
                                        <span  class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                               :class="{
                                                   'bg-green-100 text-green-800': {{ $warehouse->is_active ? 'true' : 'false' }},
                                                   'bg-red-100 text-red-800'  : {{ $warehouse->is_active ? 'false' : 'true' }}
                                               }">
                                            {{ $warehouse->is_active ? 'Sì' : 'No' }}
                                        </span>
                                    </td>
                                </tr>

                                {{-- Riga CRUD con pulsante Disattiva/Ripristina :contentReference[oaicite:1]{index=1} --}}
                                @if($canToggle && $warehouse->id !== 1)
                                    <tr x-show="openId === {{ $warehouse->id }}" x-cloak>
                                        <td :colspan="extended ? 5 : 4" class="px-6 py-3 bg-gray-200 dark:bg-gray-700">
                                            <div class="flex items-center space-x-4 text-xs">
                                                <form  action="{{ route('warehouses.update', $warehouse) }}"
                                                       method="POST"
                                                       onsubmit="return confirm('{{ $warehouse->is_active ? 'Disattivare' : 'Ripristinare' }} il magazzino?');">
                                                    @csrf
                                                    @method('PUT')
                                                    {{-- appena basta cambiare is_active --}}
                                                    <input type="hidden" name="is_active" value="{{ $warehouse->is_active ? 0 : 1 }}">
                                                    <button type="submit"
                                                            class="inline-flex items-center hover:text-{{ $warehouse->is_active ? 'red-600' : 'green-600' }}">
                                                        <i class="fas {{ $warehouse->is_active ? 'fa-ban mr-1' : 'fa-undo mr-1' }}"></i>
                                                        {{ $warehouse->is_active ? 'Disattiva' : 'Ripristina' }}
                                                    </button>
                                                </form>
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
                    {{ $warehouses->links('vendor.pagination.tailwind-compact') }}
                </div>
                {{-- ╚═══════════════════════════════════════════════════════════════════╝ --}}
            </div>
        </div>
    </div>

    {{-- ╔════════════════════════════════ Alpine JS ═══════════════════════════════╗ --}}
    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('warehouseCrud', () => ({
                // Modal
                showModal: false,

                // Gestione riga espansa e colonna tipo
                openId:   null,
                extended: false,

                openCreate() { this.showModal = true; },

                init() {
                    @if($errors->any())
                        // Riapre il modal se il backend ha ritornato errori di validazione
                        this.showModal = true;
                    @endif
                },
            }));
        });
    </script>
    @endpush
    {{-- ╚═════════════════════════════════════════════════════════════════════════╝ --}}
</x-app-layout>
