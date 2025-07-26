{{-- resources/views/livewire/warehouse/exit-table.blade.php --}}
<div>    
    {{-- ╔═════════════ KPI CARDS ═════════════╗ --}}
    <div class="py-2">
        @php
            $fasi = [
                0 => ['txt' => 'Inserito'      , 'icon' => 'fa-upload'          , 'bg' => 'bg-gray-300 dark:bg-gray-700'],
                1 => ['txt' => 'Struttura'     , 'icon' => 'fa-hammer'          , 'bg' => 'bg-yellow-100 dark:bg-yellow-700'],
                2 => ['txt' => 'Imbottitura'   , 'icon' => 'fa-feather'         , 'bg' => 'bg-green-100 dark:bg-green-700'],
                3 => ['txt' => 'Rivestimento'  , 'icon' => 'fa-couch'           , 'bg' => 'bg-blue-100  dark:bg-blue-700'],
                4 => ['txt' => 'Assemblaggio'  , 'icon' => 'fa-screwdriver-wrench', 'bg' => 'bg-indigo-100 dark:bg-indigo-700'],
                5 => ['txt' => 'Finitura'      , 'icon' => 'fa-brush'           , 'bg' => 'bg-purple-100 dark:bg-purple-700'],
                6 => ['txt' => 'Spedizione'    , 'icon' => 'fa-truck'           , 'bg' => 'bg-red-100   dark:bg-red-700'],
            ];
        @endphp

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2">
            @foreach ($fasi as $idx => $d)
                @php $sel = $phase === $idx; @endphp
                <div  tabindex="0"
                    wire:click="$set('phase', {{ $idx }})"
                    @keydown.enter.prevent="$set('phase', {{ $idx }})"
                    class="cursor-pointer rounded p-3 flex flex-col items-center justify-center
                            {{ $d['bg'] }}
                            {{ $sel ? 'ring-2 ring-indigo-500 scale-105 transition transform' : '' }}">
                    <i class="fas {{ $d['icon'] }} text-xl mb-1"></i>
                    <span class="text-xs font-semibold">{{ $d['txt'] }}</span>
                    <span class="text-lg font-bold">{{ $kpiCounts[$idx] ?? 0 }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ╔═════════════ TABELLONE ═════════════╗ --}}
    <div class="py-6" x-data="exitCrud()" @open-row.window="openId = ($event.detail === openId ? null : $event.detail)">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="p-4 overflow-x-auto">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- HEAD --}}
                        <thead class="bg-gray-300 dark:bg-gray-700 uppercase tracking-wider">
                            <tr>

                                {{-- CLIENTE --}}
                                <x-th-menu-live
                                    field="customer"
                                    label="Cliente"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :align="'left'"
                                />

                                {{-- NUMERO ORDINE --}}
                                <x-th-menu-live
                                    field="order_number"
                                    label="Nr. Ordine"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :align="'left'"
                                />

                                {{-- PRODOTTO --}}
                                <x-th-menu-live
                                    field="product"
                                    label="Prodotto"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :align="'left'"
                                />

                                {{-- DATA ORDINE --}}
                                <x-th-menu-live
                                    field="order_date"
                                    label="Data ordine"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                />

                                {{-- DATA CONSEGNA --}}
                                <x-th-menu-live
                                    field="delivery_date"
                                    label="Consegna"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                />

                                {{-- VALORE € --}}
                                <x-th-menu-live
                                    field="value"
                                    label="Valore €"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                />

                                {{-- Q.TY FASE --}}
                                <x-th-menu-live
                                    field="qty_in_phase"
                                    label="Q.ty fase"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :align="'right'"
                                />
                            </tr>
                        </thead>

                        {{-- BODY --}}
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($exitRows as $row)
                                @php
                                    $canAdvance  = auth()->user()->can('stock.exit');
                                    $canRollback = auth()->user()->can('orders.customer.rollback_item_phase');
                                    $canToggle   = $canAdvance || $canRollback;
                                @endphp

                                {{-- RIGA PRINCIPALE --}}
                                <tr  @if($canToggle)
                                         @click="$dispatch('open-row', {{ $row->id }})"
                                         class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                         :class="openId === {{ $row->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                     @endif>
                                    {{-- Cliente --}}
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ $row->customer ?? '—' }}
                                    </td>

                                    {{-- Nr. ordine --}}
                                    <td class="px-6 py-2 text-center">
                                        {{ $row->order_number ?? '—' }}
                                    </td>

                                    {{-- Prodotto (SKU - nome) --}}
                                    <td class="px-6 py-2 whitespace-nowrap"
                                        title="{{ $row->product_name }}">
                                        {{ $row->product_name ?? '—' }}
                                    </td>

                                    {{-- Data ordine / consegna --}}
                                    <td class="px-6 py-2  whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($row->order_date)->format('Y-m-d') ?? '—' }}
                                    </td>
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($row->delivery_date)->format('Y-m-d') ?? '—' }}
                                    </td>

                                    {{-- Valore € --}}
                                    <td class="px-6 py-2 text-right whitespace-nowrap">
                                        € {{ number_format($row->value, 2, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-2 text-right">{{ $row->qty_in_phase }}</td>
                                </tr>

                                {{-- RIGA TOOLBAR --}}
                                @if($canToggle)
                                    <tr x-show="openId === {{ $row->id }}" x-cloak>
                                        <td :colspan="9" class="px-6 py-3 bg-gray-200 dark:bg-gray-700">
                                            <div class="flex items-center space-x-4 text-xs">
                                                {{-- ► Avanza fase (qty default 100 %) --}}
                                                @can('stock.exit')
                                                    <button  type="button" class="inline-flex items-center hover:text-green-700"
                                                             @click.prevent="$wire.emit('open-advance', {{ $row->id }}, {{ $row->qty_in_phase }})">
                                                        <i class="fas fa-forward mr-1"></i> Avanza
                                                    </button>
                                                @endcan

                                                {{-- ↶ Rollback --}}
                                                @can('orders.customer.rollback_item_phase')
                                                    <button  type="button" class="inline-flex items-center hover:text-amber-600"
                                                             @click.prevent="$wire.emit('open-rollback', {{ $row->id }}, {{ $row->qty_in_phase }})">
                                                        <i class="fas fa-undo mr-1"></i> Rollback
                                                    </button>
                                                @endcan

                                                {{-- 📝 Note --}}
                                                <button type="button" class="inline-flex items-center hover:text-indigo-600"
                                                        @click.prevent="$wire.emit('open-note', {{ $row->id }})">
                                                    <i class="fas fa-sticky-note mr-1"></i> Note
                                                </button>

                                                {{-- 🖨 DdT --}}
                                                <button  type="button" class="inline-flex items-center hover:text-purple-600"
                                                         @click.prevent="$wire.emit('print-ddt', {{ $row->id }})">
                                                    <i class="fas fa-print mr-1"></i> DdT
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- PAGINAZIONE --}}
                <div class="flex items-center justify-between px-6 py-2">
                    <div>
                        {{ $exitRows->links('vendor.pagination.tailwind-compact') }}
                    </div>
                    <div>
                        <label class="text-xs mr-1 p-1">Righe:</label>
                        <select wire:model="perPage" class="border rounded px-5 py-1 text-xs">
                            <option value="100">100</option>
                            <option value="250">250</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
