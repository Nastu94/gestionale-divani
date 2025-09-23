{{--resources/views/livewire/warehouse/stock-levels-table.blade.php--}}
{{-- Componente Livewire per la gestione della tabella livelli di magazzino --}}
{{-- Mostra i livelli di stock per componenti, con possibilità di espandere per vedere i lotti associati --}}
{{-- Utilizza Alpine.js per la gestione dell'espansione delle righe e del drawer dei lotti --}}
<div class="py-6" x-data="{ openId: @entangle('expandedId') }" @close-row.window="openId = null">

    {{-- FLASH --}}
    <x-flash />

    {{-- TOOLBAR: switch vista --}}
    <div class="mb-3 flex items-center justify-between">
        <div class="inline-flex rounded-md shadow-sm bg-white dark:bg-gray-800 p-1">
            <button
                wire:click="$set('mode','components')"
                type="button"
                class="px-3 py-1.5 text-sm font-medium rounded-l-md border
                       {{ $mode==='components' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-300' }}">
                Componenti
            </button>
            <button
                wire:click="$set('mode','products')"
                type="button"
                class="px-3 py-1.5 text-sm font-medium rounded-r-md border-t border-b border-r
                       {{ $mode==='products' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-300' }}">
                Prodotti resi
            </button>
        </div>

        {{-- perPage (resta invariato) --}}
        <div class="text-xs">
            <label class="mr-1 p-1">Righe:</label>
            <select wire:model="perPage" class="border rounded px-5 py-1 text-xs">
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="250">250</option>
            </select>
        </div>
    </div>

    {{-- TABLE WRAPPER --}}
    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <div class="p-4 overflow-x-auto">

            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                {{-- HEAD --}}
                <thead class="bg-gray-300 dark:bg-gray-700 uppercase tracking-wider">
                    <tr>
                        <x-th-menu-live field="component_code"
                                        label="Codice"
                                        :sort="$sort" :dir="$dir"
                                        :filters="$filters" />
                        <x-th-menu-live field="component_description"
                                        label="Descrizione"
                                        :sort="$sort" :dir="$dir"
                                        :filters="$filters" />

                        @if ($mode === 'components')
                            <x-th-menu-live field="uom"
                                            label="UM"
                                            :sort="$sort" :dir="$dir"
                                            :filters="$filters"
                                            align="right" />
                        @else
                            {{-- In vista PRODOTTI: Tessuto / Colore + sort dedicati --}}
                            <x-th-menu-live field="fabric"
                                            label="Tessuto"
                                            :sort="$sort" :dir="$dir"
                                            :filters="$filters"
                                            :filterable="true"
                                            align="right" />

                            <x-th-menu-live field="color"
                                            label="Colore"
                                            :sort="$sort" :dir="$dir"
                                            :filters="$filters"
                                            :filterable="true"
                                            align="right" />
                        @endif

                        <x-th-menu-live field="quantity"
                                        label="Totale"
                                        :sort="$sort" :dir="$dir"
                                        :filters="$filters"
                                        :filterable="false"
                                        align="right" />

                        <x-th-menu-live field="reserved_quantity"
                                        label="Riservato"
                                        :sort="$sort" :dir="$dir"
                                        :filters="$filters"
                                        :filterable="false"
                                        align="right" />
                    </tr>
                </thead>

                {{-- BODY --}}
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($levels as $level)
                        <tr wire:key="row-{{ $level->id }}"
                            @if ($mode === 'components')
                                x-data
                                x-on:click="$dispatch('open-lots', {{ $level->id }})"
                                class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                            @else
                                class="hover:bg-gray-100 dark:hover:bg-gray-700"
                            @endif
                        >
                            {{-- Codice / Descrizione --}}
                            <td class="px-6 py-2">
                                {{ $mode==='components' ? $level->component_code : $level->product_code }}
                            </td>
                            <td class="px-6 py-2">
                                {{ $mode==='components' ? $level->component_description : $level->product_description }}
                            </td>

                            {{-- Colonne variabili --}}
                            @if ($mode === 'components')
                                <td class="px-6 py-2 text-left">{{ strtoupper($level->uom) }}</td>
                            @else
                                <td class="px-6 py-2 text-left">{{ $level->fabric_name ?? '—' }}</td>
                                <td class="px-6 py-2 text-left">{{ $level->color_name  ?? '—' }}</td>
                            @endif

                            {{-- Quantità / Riservato --}}
                            <td class="px-6 py-2 text-center">{{ $level->quantity }}</td>
                            <td class="px-6 py-2 text-center">{{ $level->reserved_quantity }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $mode==='components' ? 5 : 6 }}"
                                class="px-6 py-2 text-center text-gray-500">
                                Nessun risultato trovato.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINAZIONE --}}
        <div class="flex items-center justify-between px-6 py-2">
            <div>{{ $levels->links('vendor.pagination.tailwind-compact') }}</div>
            {{-- perPage già nella toolbar in alto --}}
        </div>
    </div>

    {{-- DRAWER LOTTI: solo per COMPONENTI --}}
    @if ($mode === 'components')
    <div  x-data="{ open:false, spin:false, lots:[] }"
          x-cloak
          x-on:open-lots.window="
                open=true; spin=true;
                $wire.call('toggle', $event.detail)
                    .then(() => { lots = $wire.lots[$event.detail]; spin=false; });">

        <div  x-show="open" x-transition.opacity
              class="fixed inset-0 bg-black/50 z-40" @click="open=false"></div>

        <aside x-show="open"
               x-transition:enter="transition duration-200 transform"
               x-transition:leave="transition duration-150 transform"
               x-transition:enter-start="translate-y-full"
               x-transition:enter-end="translate-y-0"
               x-transition:leave-start="translate-y-0"
               x-transition:leave-end="translate-y-full"
               class="fixed inset-x-0 bottom-0 bg-white dark:bg-gray-800
                      max-h-[60vh] rounded-t-2xl shadow-lg z-50 overflow-auto">

            <header class="p-4 font-semibold text-lg flex justify-between">
                Lotti componente
                <button @click="open=false"><i class="fas fa-times"></i></button>
            </header>

            <div class="px-5 pt-2 pb-6 md:pb-8 [padding-bottom:env(safe-area-inset-bottom)]">
                <div x-show="spin" class="py-6 text-center text-sm">
                    <i class="fas fa-circle-notch fa-spin mr-1"></i>Caricamento lotti…
                </div>

                <table x-show="!spin" class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-200 dark:bg-gray-700 text-left">
                        <tr>
                            <th class="px-4 py-2">Interno</th>
                            <th class="px-4 py-2">Fornitore</th>
                            <th class="px-4 py-2 text-right">Q.tà</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="lot in lots" :key="lot.internal_lot_code">
                            <tr class="divide-x">
                                <td class="px-4 py-1" x-text="lot.internal_lot_code"></td>
                                <td class="px-4 py-1" x-text="lot.supplier_lot_code || '—'"></td>
                                <td class="px-4 py-1 text-right" x-text="lot.quantity"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </aside>
    </div>
    @endif

</div>
