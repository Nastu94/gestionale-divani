{{-- resources/views/pages/orders/index-customers.blade.php --}}

<x-app-layout>
    {{-- ========== HEADER ========== --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Ordini Cliente') }}
            </h2>
            <x-dashboard-tiles />
        </div>

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

    {{-- ========== MAIN ========== --}}
    <div class="py-6">
        <div  x-data="{
                    openId: null,

                    openCreate() {
                        window.dispatchEvent(new CustomEvent('open-customer-order-modal'))
                    },
                    openEdit(id) {
                        window.dispatchEvent(
                            new CustomEvent('open-customer-order-modal', { detail: { orderId: id } })
                        )
                    },
                    sidebarOpen        : false,
                    sidebarLines       : [],
                    sidebarOrderNumber : null,
                    sidebarLoading     : false,
                    formatCurrency(v)  { return Intl.NumberFormat('it-IT', { minimumFractionDigits: 2 }).format(v) },
                    openSidebar(id, num) {
                        this.sidebarOpen        = true
                        this.sidebarLoading     = true
                        this.sidebarLines       = []
                        this.sidebarOrderNumber = num

                        fetch(`/orders/customer/${id}/lines`, {
                            headers     : { Accept: 'application/json' },
                            credentials : 'same-origin'
                        })
                        .then(r => { if (!r.ok) throw new Error('Errore ' + r.status); return r.json() })
                        .then(rows => { this.sidebarLines = rows; this.sidebarLoading = false })
                        .catch(e   => { alert('Impossibile caricare le righe'); console.error(e); this.sidebarLoading = false })
                    },
                }"
                class="max-w-full mx-auto sm:px-6 lg:px-8"
        >
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                {{-- Toolbar --}}
                <div class="flex justify-end m-2 p-2">
                    @can('orders.customer.create')
                        <button  @click="openCreate"
                                 class="inline-flex items-center m-2 px-3 py-1.5 bg-purple-600 rounded-md
                                        text-xs font-semibold text-white uppercase hover:bg-purple-500
                                        focus:outline-none focus:ring-2 focus:ring-purple-300 transition">
                            <i class="fas fa-plus mr-1"></i> Nuovo
                        </button>
                    @endcan
                </div>

                {{-- ====== TABELLA ====== --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700 uppercase tracking-wider">
                            <tr>
                                <x-th-menu field="id" label="Ordine #"
                                           :sort="$sort" :dir="$dir" :filters="$filters"
                                           reset-route="orders.customer.index"
                                           :filterable="false" align="left" />
                                <x-th-menu field="customer" label="Cliente"
                                           :sort="$sort" :dir="$dir" :filters="$filters"
                                           reset-route="orders.customer.index" />
                                <x-th-menu field="ordered_at" label="Data Ordine"
                                           :sort="$sort" :dir="$dir" :filters="$filters"
                                           reset-route="orders.customer.index"
                                           :filterable="false" />
                                <x-th-menu field="delivery_date" label="Data Consegna"
                                           :sort="$sort" :dir="$dir" :filters="$filters"
                                           reset-route="orders.customer.index"
                                           :filterable="false" />
                                <x-th-menu field="total" label="Valore (€)"
                                           :sort="$sort" :dir="$dir" :filters="$filters"
                                           reset-route="orders.customer.index"
                                           :filterable="false" align="left" />
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($orders as $order)
                                @php
                                    $started      = (bool) $order->has_started_prod;          // nuovo flag
                                    $canEdit      = auth()->user()->can('orders.customer.update')  && ! $started;
                                    $canDelete    = auth()->user()->can('orders.customer.delete')  && ! $started;
                                    $canCrud      = $canEdit || $canDelete;                   // apre/chiude toolbar
                                @endphp

                                {{-- RIGA PRINCIPALE --}}
                                <tr  @if ($canCrud || auth()->user()->can('orders.customer.view'))
                                         @click="openId = (openId === {{ $order->id }} ? null : {{ $order->id }})"
                                         class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                         :class="openId === {{ $order->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                     @endif
                                >
                                    <td class="px-6 py-2 text-center">
                                        {{ $order->orderNumber->number }}
                                        @if ($order->has_started_prod)
                                            <i class="fas fa-industry text-xs text-purple-600 ml-1" title="Produzione in corso"></i>
                                        @endif
                                    </td>
                                    <td class="px-6 py-2">
                                        {{ $order->customer      ? $order->customer->company
                                        : ($order->occasionalCustomer->company ?? '—') }}
                                    </td>
                                    <td class="px-6 py-2">{{ $order->ordered_at?->format('d/m/Y') }}</td>
                                    <td class="px-6 py-2">{{ $order->delivery_date?->format('d/m/Y') }}</td>
                                    <td class="px-6 py-2 text-center">{{ number_format($order->total, 2, ',', '.') }}</td>
                                </tr>

                                {{-- RIGA ESPANSA CON AZIONI --}}
                                @if ($canCrud || auth()->user()->can('orders.customer.view'))
                                    <tr x-show="openId === {{ $order->id }}" x-cloak>
                                        <td :colspan="5" class="px-6 py-3 bg-gray-200 dark:bg-gray-700">
                                            <div class="flex items-center space-x-4 text-xs">

                                                {{-- Visualizza --}}
                                                @can('orders.customer.view')
                                                    <button type="button"
                                                            @click.stop="openSidebar({{ $order->id }}, {{ $order->number }})"
                                                            class="inline-flex items-center hover:text-blue-600">
                                                        <i class="fas fa-eye mr-1"></i> Visualizza
                                                    </button>
                                                @endcan

                                                {{-- Modifica --}}
                                                @if ($canEdit)
                                                    <button  type="button"
                                                            @click.stop="openEdit({{ $order->id }})"
                                                            class="inline-flex items-center hover:text-green-600">
                                                        <i class="fas fa-pen mr-1"></i> Modifica
                                                    </button>
                                                @endif

                                                {{-- Cancella --}}
                                                @if ($canDelete)
                                                    <form  method="POST"
                                                           action="{{ route('orders.customer.destroy', $order) }}"
                                                           onsubmit="return confirm('Sei sicuro di voler eliminare quest\'ordine?')"
                                                           class="inline">
                                                        @csrf @method('DELETE')
                                                        <button type="submit"
                                                                class="inline-flex items-center hover:text-red-600">
                                                            <i class="fas fa-trash mr-1"></i> Elimina
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

                {{-- PAGINAZIONE --}}
                <div class="mt-4">
                    {{ $orders->links() }}
                </div>
            </div>

            {{-- Modale per creazione/modifica ordine --}}
            <x-customer-order-create-modal />

            {{-- ===== OVERLAY SIDEBAR RIGHE ORDINE ===== --}}
            <div  x-show="sidebarOpen"
                  x-cloak
                  class="fixed inset-0 flex z-50"
                  x-transition.opacity>
                {{-- backdrop --}}
                <div class="flex-1 bg-black/50" @click="sidebarOpen = false"></div>

                {{-- pannello --}}
                <div  class="w-full max-w-2xl bg-white dark:bg-gray-900 shadow-xl overflow-y-auto"
                      x-transition:enter="transition transform duration-300"
                      x-transition:enter-start="translate-x-full"
                      x-transition:leave="transition transform duration-300"
                      x-transition:leave-end="translate-x-full"
                >
                    <div class="p-6 border-b flex justify-between items-center">
                        <h3 class="text-lg font-semibold">
                            Righe ordine # <span x-text="sidebarOrderNumber"></span>
                        </h3>
                        <button @click="sidebarOpen = false">
                            <i class="fas fa-times text-gray-600"></i>
                        </button>
                    </div>

                    {{-- spinner overlay --}}
                    <div  x-show="sidebarLoading"
                          x-transition.opacity
                          x-cloak
                          class="absolute inset-0 flex items-center justify-center bg-white/70">
                        <i class="fas fa-circle-notch fa-spin text-3xl text-gray-600"></i>
                    </div>

                    {{-- tabella righe --}}
                    <div x-show="sidebarLines.length > 0" class="p-4">
                        <table class="w-full text-sm border divide-y">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-2 py-1">Codice</th>
                                    <th class="px-2 py-1">Articolo</th>
                                    <th class="px-2 py-1 w-14 text-right">Q.tà</th>
                                    <th class="px-2 py-1 w-14">Unit</th>
                                    <th class="px-2 py-1 w-20 text-right">Prezzo</th>
                                    <th class="px-2 py-1 w-24 text-right">Subtot.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, idx) in sidebarLines" :key="row.uid ?? (row.code + '-' + idx)">
                                    <tr
                                        :class="{
                                            'font-semibold bg-purple-50' : row.type === 'product',
                                            'text-gray-600 italic text-xs'       : row.type === 'component'
                                        }"
                                    >
                                        <td class="px-2 py-1"
                                            :class="row.type === 'component' ? 'pl-6' : ''"
                                            x-text="row.code">
                                        </td>
                                        <td class="px-2 py-1" x-text="row.desc"></td>
                                        <td class="px-2 py-1 text-right" x-text="row.qty"></td>
                                        <td class="px-2 py-1 uppercase"   x-text="row.unit"></td>
                                        <td class="px-2 py-1 text-right"
                                            x-text="formatCurrency(row.price)">
                                        </td>
                                        <td class="px-2 py-1 text-right"
                                            x-text="formatCurrency(row.subtot)">
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            {{-- /sidebar --}}
        </div>
    </div>
</x-app-layout>
