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

    <!-- ===== OVERLAY SIDEBAR DETTAGLIO RESO ===== -->
    <div
        x-data="returnsSidebar()"
        @open-return-details.window="openSidebar($event.detail.id)"
    >
        <div
            x-show="sidebarOpen"
            x-cloak
            class="fixed inset-0 flex z-50"
            x-transition.opacity
        >
            <!-- Backdrop -->
            <div class="flex-1 bg-black/50" @click="sidebarOpen = false"></div>

            <!-- Pannello -->
            <div
                class="relative w-full max-w-2xl bg-white dark:bg-gray-900 shadow-xl overflow-y-auto"
                x-transition:enter="transition transform duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:leave="transition transform duration-300"
                x-transition:leave-end="translate-x-full"
            >
                <!-- Header -->
                <div class="p-6 border-b flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold">
                            Reso # <span x-text="sb.header.number"></span>
                        </h3>
                        <div class="mt-1 text-xs text-gray-500 space-x-2">
                            <span>Data: <span x-text="sb.header.return_date_fmt"></span></span>
                            <template x-if="sb.header.order_number">
                                <span>· Ordine: <span class="font-medium" x-text="sb.header.order_number"></span></span>
                            </template>
                        </div>
                        <div class="text-xs text-gray-500" x-show="sb.header.customer_label">
                            Cliente: <span x-text="sb.header.customer_label"></span>
                        </div>
                    </div>
                    <button @click="sidebarOpen = false">
                        <i class="fas fa-times text-gray-600"></i>
                    </button>
                </div>

                <!-- Spinner overlay -->
                <div
                    x-show="sidebarLoading"
                    x-transition.opacity
                    x-cloak
                    class="absolute inset-0 flex items-center justify-center bg-white/70"
                >
                    <i class="fas fa-circle-notch fa-spin text-3xl text-gray-600"></i>
                </div>

                <!-- Corpo -->
                <div class="p-4 space-y-4">
                    <!-- Note generali -->
                    <template x-if="sb.header.notes">
                        <div class="rounded border border-purple-200 bg-purple-50/70 p-3">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fas fa-sticky-note text-purple-600"></i>
                                <span class="font-semibold">Note generali</span>
                            </div>
                            <p class="text-sm text-purple-900 whitespace-pre-line" x-text="sb.header.notes"></p>
                        </div>
                    </template>

                    <!-- Tabella righe -->
                    <div x-show="sb.lines.length > 0">
                        <table class="w-full text-xs border divide-y">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-2 py-1">Codice</th>
                                    <th class="px-2 py-1">Articolo</th>
                                    <th class="px-2 py-1">Tess./Col.</th>
                                    <th class="px-2 py-1 text-right w-14">Q.tà</th>
                                    <th class="px-2 py-1 text-center w-16">In mag.</th>
                                    <th class="px-2 py-1 w-20">Cond.</th>
                                    <th class="px-2 py-1 w-24">Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="r in sb.lines" :key="r.id">
                                    <tr>
                                        <td class="px-2 py-1" x-text="r.product_sku"></td>
                                        <td class="px-2 py-1" x-text="r.product_name"></td>
                                        <td class="px-2 py-1" x-text="r.fabric_color_label || '—'"></td>
                                        <td class="px-2 py-1 text-right" x-text="r.quantity"></td>
                                        <td class="px-2 py-1 text-center">
                                            <span
                                                class="inline-flex text-[11px] rounded-full px-2 py-0.5"
                                                :class="r.restock ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                                x-text="r.restock ? 'Sì' : 'No'"
                                            ></span>
                                        </td>
                                        <td class="px-2 py-1" x-text="r.condition || '—'"></td>
                                        <td class="px-2 py-1" x-text="r.reason || '—'"></td>
                                    </tr>
                                </template>
                                <tr x-show="!sb.lines.length">
                                    <td colspan="7" class="px-2 py-6 text-center text-gray-400">Nessuna riga</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Note righe -->
                    <template x-if="sb.lineNotes.length">
                        <div>
                            <h4 class="text-sm font-semibold mb-1">Note righe</h4>
                            <ul class="space-y-3">
                                <template x-for="ln in sb.lineNotes" :key="ln.id">
                                    <li class="rounded border p-3">
                                        <div class="text-sm font-medium" x-text="ln.product_sku + ' · ' + ln.product_name"></div>
                                        <div class="text-xs text-gray-500 mb-1">
                                            Q.tà: <span x-text="ln.quantity"></span>
                                            <span x-show="ln.fabric_color_label"> · <span x-text="ln.fabric_color_label"></span></span>
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-line" x-text="ln.note"></p>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('returnsSidebar', () => ({
                sidebarOpen: false,
                sidebarLoading: false,

                sb: {
                    header: {
                    id: null,
                    number: null,
                    return_date: null,
                    return_date_fmt: null,
                    customer_label: null,
                    order_number: null,
                    notes: null,
                    },
                    lines: [],
                    lineNotes: [],
                },

                reset() {
                    this.sb = {
                    header: {
                        id: null,
                        number: null,
                        return_date: null,
                        return_date_fmt: null,
                        customer_label: null,
                        order_number: null,
                        notes: null,
                    },
                    lines: [],
                    lineNotes: [],
                    };
                },

                async openSidebar(id) {
                    this.reset();
                    this.sidebarOpen = true;
                    this.sidebarLoading = true;

                    try {
                        const res = await fetch(`/returns/${id}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) throw new Error('HTTP ' + res.status);

                        const j = await res.json();
                        const h = j.return ?? j;

                        // ---- intestazione ----
                        const cust  = h.customer ?? {};
                        const email = (cust.email ?? h.customer_email ?? '').trim();
                        const ship  = (
                            h.customer_shipping_address ??
                            cust.shipping_address_string ??
                            cust.shipping_address ??
                            ''
                        ).toString().trim();

                        this.sb.header = {
                            id: h.id ?? null,
                            number: h.number ?? null,
                            return_date: h.return_date ?? null,
                            return_date_fmt:
                            h.return_date_formatted ?? ((h.return_date ?? '').slice(0, 10)),
                            customer_label: [
                            (cust.company ?? cust.label ?? '').trim(),
                            email,
                            ship,
                            ]
                            .filter(Boolean)
                            .join(' – '),
                            order_number: h.order?.number ?? h.order_number ?? null,
                            notes: h.notes ?? null,
                        };

                        // ---- righe ----
                        const lines = (h.lines ?? []).map((r) => {
                            const p = r.product ?? {};
                            const fabric = r.fabric_name ?? r.fabric ?? null;
                            const color  = r.color_name  ?? r.color  ?? null;

                            return {
                            id: r.id,
                            product_name: p.name ?? r.product_name ?? '—',
                            product_sku:  p.sku  ?? r.product_sku  ?? '—',
                            quantity: r.quantity,
                            fabric_color_label:
                                [fabric, color].filter(Boolean).join(' / ') || null,
                            restock: !!r.restock,
                            condition: r.condition ?? null,
                            reason: r.reason ?? null,
                            note: r.note ?? null,
                            };
                        });

                        this.sb.lines = lines;
                        this.sb.lineNotes = lines.filter(
                            (x) => x.note && String(x.note).trim() !== ''
                        );
                    } catch (e) {
                        console.error(e);
                        this.sidebarOpen = false;
                        alert('Impossibile caricare il dettaglio del reso.');
                    } finally {
                        this.sidebarLoading = false;
                    }
                },
            }));
        });
    </script>
</x-app-layout>
