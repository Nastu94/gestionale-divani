<div class="py-6" x-data="{ openId: @entangle('expandedId') }"
     @close-row.window="openId = null">

    {{-- FLASH --}}
    <x-flash />

    {{-- TABLE WRAPPER --}}
    <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
        <div class="p-4 overflow-x-auto">

            {{-- TABELLONE --}}
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
                        <x-th-menu-live field="uom"
                                        label="UM"
                                        :sort="$sort" :dir="$dir"
                                        :filters="$filters"
                                        align="right" />
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
                    @foreach ($levels as $level)
                        {{-- RIGA PRINCIPALE --}}
                        <tr  wire:key="row-{{ $level->id }}"
                            x-data
                            x-on:click="$dispatch('open-lots', {{ $level->id }})"
                            class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700">
                            <td class="px-6 py-2">{{ $level->component_code }}</td>
                            <td class="px-6 py-2">{{ $level->component_description }}</td>
                            <td class="px-6 py-2 text-left">{{ strtoupper($level->uom) }}</td>
                            <td class="px-6 py-2 text-center">{{ $level->quantity }}</td>
                            <td class="px-6 py-2 text-center">{{ $level->reserved_quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- PAGINAZIONE + perPage --}}
        <div class="flex items-center justify-between px-6 py-2">
            <div>{{ $levels->links('vendor.pagination.tailwind-compact') }}</div>

            <div>
                <label class="text-xs mr-1 p-1">Righe:</label>
                <select wire:model="perPage" class="border rounded px-5 py-1 text-xs">
                    <option value="50">50</option>
                    <option value="100">100</option>
                    <option value="250">250</option>
                </select>
            </div>
        </div>
    </div>

    {{-- DRAWER fuoricontesto – mettilo in fondo al component --}}
    <div  x-data="{ open:false, spin:false, lots:[] }"
        x-cloak
        x-on:open-lots.window="
                open=true; spin=true;
                $wire.call('toggle', $event.detail)
                    .then(r => { lots = $wire.lots[$event.detail]; spin=false; });">

        {{-- backdrop --}}
        <div  x-show="open"
            x-transition.opacity
            class="fixed inset-0 bg-black/50 z-40"
            @click="open=false"></div>

        {{-- pannello --}}
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

            {{-- wrapper con padding per staccare il contenuto dal fondo --}}
            <div class="px-5 pt-2 pb-6 md:pb-8 [padding-bottom:env(safe-area-inset-bottom)]">
                {{-- spinner --}}
                <div x-show="spin" class="py-6 text-center text-sm">
                    <i class="fas fa-circle-notch fa-spin mr-1"></i>Caricamento lotti…
                </div>

                {{-- tabella lotti --}}
                <table x-show="!spin"
                    class="min-w-full text-sm divide-y divide-gray-200">
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

</div>
