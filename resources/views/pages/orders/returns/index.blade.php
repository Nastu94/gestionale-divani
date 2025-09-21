{{-- resources/views/pages/orders/returns/index.blade.php --}}
{{-- 
    Vista Index Resi:
    - Usa <x-app-layout>
    - Tabella con header filtrabili/sortabili tramite il tuo <x-th-menu>
    - Colonne: N. Reso, Data, Cliente, Stato, CRUD
    - Riga espandibile con azioni (Visualizza/Modifica/Elimina), stile "riga CRUD" come nelle altre viste
--}}

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 leading-tight">
            {{ __('Resi Cliente') }}
        </h2>

        {{-- alert successo --}}
        @if (session('success'))
            <div  x-data="{ show: true }"
                  x-init="setTimeout(() => show = false, 10000)"
                  x-show="show"
                  x-transition.opacity.duration.500ms
                  class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mt-2"
                  role="alert">
                <i class="fas fa-check-circle mr-1"></i>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        {{-- alert errore --}}
        @if (session('error'))
            <div  x-data="{ show: true }"
                  x-init="setTimeout(() => show = false, 10000)"
                  x-show="show"
                  x-transition.opacity.duration.500ms
                  class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-2"
                  role="alert">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8" x-data="{ openId: null }">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                {{-- Bottone "Nuovo Reso" che aprirà il tuo modale --}}
                <div class="flex justify-end m-2 p-2">
                    @can('orders.customer.returns_manage')
                        <button type="button"
                                @click="window.dispatchEvent(new CustomEvent('open-return-create'))"
                                class="inline-flex items-center m-2 px-3 py-1.5 bg-purple-600 rounded-md
                                    text-xs font-semibold text-white uppercase hover:bg-purple-500
                                    focus:outline-none focus:ring-2 focus:ring-purple-300 transition">
                            <i class="fas fa-plus mr-1"></i> Nuovo Reso
                        </button>
                    @endcan
                </div>

                {{-- ====== TABELLA ====== --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700 uppercase tracking-wider">
                            <tr>
                                {{-- N. Reso (filterable + sort) --}}
                                <x-th-menu field="number" label="N. Reso"
                                        :sort="$sort" :dir="$dir" :filters="$filters"
                                        reset-route="returns.index"
                                        :filterable="true" align="left" />

                                {{-- Data reso (solo sort, niente filtro a colonna per restare coerenti con tua richiesta minimal) --}}
                                <x-th-menu field="return_date" label="Data"
                                        :sort="$sort" :dir="$dir" :filters="$filters"
                                        reset-route="returns.index"
                                        :filterable="false" />

                                {{-- Cliente (filterable + sort su customers.name) --}}
                                <x-th-menu field="customer" label="Cliente"
                                        :sort="$sort" :dir="$dir" :filters="$filters"
                                        reset-route="returns.index"
                                        :filterable="true" />

                                {{-- Stato (no filtro/sort: badge derivato) --}}
                                <th class="px-6 py-2 text-left">Stato</th>
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($returns as $ret)
                                @php
                                    $canCrud = auth()->user()->can('orders.customer.returns_manage');
                                    $statusLabel = ($ret->restock_lines_count ?? 0) > 0 ? 'in magazzino' : 'solo amministrativo';
                                @endphp

                                {{-- RIGA PRINCIPALE --}}
                                <tr @if ($canCrud)
                                        @click="openId = (openId === {{ $ret->id }} ? null : {{ $ret->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $ret->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    {{-- N. Reso --}}
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ $ret->number }}
                                    </td>

                                    {{-- Data (dd/mm/YYYY) --}}
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ optional($ret->return_date)->format('d/m/Y') }}
                                    </td>

                                    {{-- Cliente --}}
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ $ret->customer?->company ?? '—' }}
                                    </td>

                                    {{-- Stato --}}
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            {{ $statusLabel === 'in magazzino'
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-300' }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                </tr>

                                {{-- RIGA ESPANSA CON AZIONI (stile riga CRUD) --}}
                                @if ($canCrud)
                                    <tr x-show="openId === {{ $ret->id }}" x-cloak>
                                        <td :colspan="5" class="px-6 py-3 bg-gray-200 dark:bg-gray-700">
                                            <div class="flex items-center space-x-4 text-xs">
                                                {{-- Visualizza (apre sidebar DETTAGLIO RESO, non cliente) --}}
                                                <button type="button"
                                                        @click.stop="$dispatch('open-return-details', { id: {{ $ret->id }} })"
                                                        class="inline-flex items-center hover:text-blue-600">
                                                    <i class="fas fa-eye mr-1"></i> Visualizza
                                                </button>

                                                {{-- Modifica (riapre modale in modalità edit) --}}
                                                <button type="button"
                                                        @click.stop="$dispatch('open-return-edit', { id: {{ $ret->id }} })"
                                                        class="inline-flex items-center hover:text-green-600">
                                                    <i class="fas fa-pen mr-1"></i> Modifica
                                                </button>

                                                {{-- Cancella --}}
                                                <form  method="POST"
                                                    action="{{ route('returns.destroy', $ret) }}"
                                                    onsubmit="return confirm('Eliminare il reso {{ $ret->number }}?')"
                                                    class="inline">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center hover:text-red-600">
                                                        <i class="fas fa-trash mr-1"></i> Elimina
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach

                            {{-- RIGA NESSUN RISULTATO --}}
                            @if ($returns->isEmpty())
                                <tr>
                                    <td colspan="5" class="px-6 py-2 text-center text-gray-500">Nessun risultato trovato.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>

                    {{-- Paginazione --}}
                    <div class="px-4 py-3">
                        {{ $returns->links('vendor.pagination.tailwind-compact') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-return-create-modal
        :customers="$customers"
        :products="$products"
        :fabrics="$fabrics"
        :colors="$colors"
        :warehouses="$warehouses"
        :returnWarehouseId="$returnWarehouseId"
    />
</x-app-layout>
