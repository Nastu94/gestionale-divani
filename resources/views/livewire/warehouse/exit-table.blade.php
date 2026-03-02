{{-- resources/views/livewire/warehouse/exit-table.blade.php --}}
<div>

    {{-- ╔═════════════ KPI CARDS ═════════════╗ --}}
    <div class="py-2">
        @php
            $fasi = [
                0 => ['txt' => 'Inserito'      , 'icon' => 'fa-upload'          , 'bg' => 'bg-gray-300 dark:bg-gray-700'],
                1 => ['txt' => 'Taglio'        , 'icon' => 'fa-cut'             , 'bg' => 'bg-blue-100  dark:bg-blue-700'],
                2 => ['txt' => 'Cucito'        , 'icon' => 'fa-thumbtack'        , 'bg' => 'bg-indigo-100 dark:bg-indigo-700'],
                3 => ['txt' => 'Fusto'         , 'icon' => 'fa-hammer'          , 'bg' => 'bg-yellow-100 dark:bg-yellow-700'],
                4 => ['txt' => 'Spugna'        , 'icon' => 'fa-feather'         , 'bg' => 'bg-green-100 dark:bg-green-700'],
                5 => ['txt' => 'Assemblaggio'  , 'icon' => 'fa-screwdriver-wrench'           , 'bg' => 'bg-purple-100 dark:bg-purple-700'],
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
    
    {{-- ╔═════════════ FLASH MESSAGES ════════════╗ --}}
    <x-flash :canForceReservation="$canForceReservation" :forceMissingComponents="$forceMissingComponents" />

    {{-- ╔═════════════ TABELLONE ═════════════╗ --}}
    <div class="py-6" x-data="exitCrud()" @open-row.window="openId = ($event.detail === openId ? null : $event.detail)" @close-row.window="openId = null">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="p-4 overflow-x-auto">
                    {{-- ─────────── Toolbar azioni globali ─────────── --}}
                    <div class="flex justify-end mb-2">
                        <button  wire:click="$refresh"
                                class="inline-flex items-center px-3 py-1.5
                                        bg-indigo-600 hover:bg-indigo-500
                                        text-xs font-semibold text-white rounded-md">

                            <i class="fas fa-sync-alt mr-1"></i> Aggiorna

                            {{-- spinner mentre Livewire elabora --}}
                            <span wire:loading.inline wire:target="$refresh" class="ml-2">
                                <i class="fas fa-circle-notch fa-spin"></i>
                            </span>
                        </button>
                    </div>
                    
                    {{-- ─────────── Tabella dati ─────────── --}}
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

                                {{-- ZONA SPEDIZIONE --}}
                                <x-th-menu-live
                                    field="shipping_zone"
                                    label="Zona spedizione"
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
                                    // permessi grezzi
                                    $canAdvanceRaw  = auth()->user()->can('stock.exit');
                                    $canRollbackRaw = auth()->user()->can('orders.customer.rollback_item_phase');

                                    // logica di fase
                                    $canAdvance  = $canAdvanceRaw  && $phase < 6;   // no “Avanza” se già in Spedizione
                                    $canRollback = $canRollbackRaw && $phase > 0;   // no “Rollback” in Inserito
                                    $showDdT     =                ($phase == 6);    // DdT solo in Spedizione
                                    $showWorkOrder = ($phase < 6);    // Work Order solo se non in Spedizione

                                    $canToggle   = $canAdvance || $canRollback || $showDdT;
                                @endphp

                                {{-- RIGA PRINCIPALE --}}
                                <tr  wire:key="row-{{ $row->id }}"
                                    @if($canToggle)
                                         @click="$dispatch('open-row', {{ $row->id }})"
                                         class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                         :class="openId === {{ $row->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                     @endif>
                                    {{-- Cliente --}}
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ $row->customer ?? '—' }}
                                    </td>

                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ $row->shipping_zone ?? '—' }}
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
                                    <tr wire:key="tb-{{ $row->id }}" x-show="openId === {{ $row->id }}" x-cloak>
                                        <td :colspan="8" class="px-6 py-3 bg-gray-200 dark:bg-gray-700">
                                            <div class="flex items-center space-x-4 text-xs">
                                                {{-- ► Avanza fase (qty default 100 %) --}}
                                                @if($canAdvance)
                                                    <button type="button"
                                                            class="inline-flex items-center hover:text-green-700"
                                                            wire:click="openAdvance({{ $row->id }}, {{ $row->qty_in_phase }})">
                                                        <i class="fas fa-forward mr-1"></i> Avanza
                                                    </button>
                                                @endif

                                                {{-- ↶ Rollback --}}
                                                @if($canRollback)
                                                    <button type="button"
                                                            class="inline-flex items-center hover:text-amber-600"
                                                            wire:click="openRollback({{ $row->id }}, {{ $row->qty_in_phase }})">
                                                        <i class="fas fa-undo mr-1"></i> Rollback
                                                    </button>
                                                @endif

                                                {{-- 🖨 Buono produzione/magazzino (solo fasi 0..5) --}}
                                                @if($showWorkOrder)
                                                    <button type="button"
                                                            class="inline-flex items-center hover:text-purple-600"
                                                            wire:click.stop="printWorkOrder({{ $row->order_id }})">
                                                        <i class="fas fa-print mr-1"></i> Stampa buono
                                                    </button>

                                                    <button type="button"
                                                            class="inline-flex items-center hover:text-indigo-600"
                                                            wire:click.stop="openWorkOrderDrawer({{ $row->order_id }})">
                                                        <i class="fas fa-list mr-1"></i> Mostra buono
                                                    </button>
                                                @endif

                                                {{-- 🖨 DdT / Mostra DDT --}}
                                                @if($showDdT)
                                                    <button type="button"
                                                            class="inline-flex items-center hover:text-purple-600"
                                                            wire:click.stop="printDdt({{ $row->id }})">
                                                        <i class="fas fa-print mr-1"></i> Stampa DdT
                                                    </button>

                                                    <button type="button"
                                                            class="inline-flex items-center hover:text-indigo-600"
                                                            wire:click.stop="openDdtDrawer({{ $row->order_id }})">
                                                        <i class="fas fa-list mr-1"></i> Mostra DDT
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach

                            {{-- RIGA NESSUN RISULTATO --}}
                            @if ($exitRows->isEmpty())
                                <tr>
                                    <td colspan="8" class="px-6 py-2 text-center text-gray-500">Nessun risultato trovato.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                {{-- PAGINAZIONE --}}
                <div class="flex items-center justify-between px-6 py-2">
                    <div>
                        {{ $exitRows->links('vendor.livewire.tailwind-compact') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{--  Modal Avanza  ----------------------------------------------------}}
    <dialog  wire:ignore
            x-data="advanceModal()"
            x-init="
                dlg = $el;                   /*  inizializza subito  */
                window.addEventListener('show-adv-modal', e => open(e.detail))
            "
            @click.outside="close"           {{-- ora il listener globale è in x-init  --}}
            @keydown.escape.window="close"
            class="rounded-lg shadow-lg w-80 max-w-full p-5
                    bg-white dark:bg-gray-800 backdrop:bg-black/40">

        <h2 class="text-lg font-semibold mb-4">Avanza fase</h2>

        <form @submit.prevent="confirm" class="space-y-4">
            <div>
                <label class="block text-sm mb-1">
                    Quantità (max <span x-text="max"></span>)
                </label>

                <input type="number" step="1" min="1"
                    x-model.number="qty" required 
                    class="w-full border rounded px-2 py-1
                            focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div>
                <label class="block text-sm mb-1">
                    Operatore
                </label>
                <input type="text" maxlength="255"
                    x-model.trim="operator"
                    class="w-full border rounded px-2 py-1
                            focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="flex justify-end gap-2 text-sm">
                <button type="button" @click="close"
                        class="px-3 py-1 bg-gray-300 rounded">Annulla</button>
                <button type="submit"
                        class="px-3 py-1 bg-green-600 text-white rounded">
                    Conferma
                </button>
            </div>
        </form>
    </dialog>

{{-- Modal Forza Prenotazione (preview) ------------------------------------- --}}
<dialog  wire:ignore.self
        x-data="forceReservationModal()"
        x-init="init()"
        @click.outside="close"
        @keydown.escape.window="close"
        class="rounded-lg shadow-lg w-[44rem] max-w-full p-5
               bg-white dark:bg-gray-800 backdrop:bg-black/40">

    <h2 class="text-lg font-semibold mb-2">Forza prenotazione</h2>
    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
        Stai per riallocare componenti per consentire l’avanzamento di fase.
        Prima di confermare, controlla il riepilogo.
    </p>

    @if(!empty($forcePlan))
        <div class="space-y-5 text-sm">

            {{-- 1) Componenti che servono --}}
            <div>
                <div class="font-semibold mb-1">Componenti necessari</div>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($forcePlan['missing'] as $m)
                        <li>
                            <span class="font-semibold">{{ $m['code'] }}</span>
                            — mancano <span class="font-semibold">{{ rtrim(rtrim(number_format($m['missing'], 4, '.', ''), '0'), '.') }}</span>
                            <span class="text-xs text-gray-500">
                                (richiesti: {{ rtrim(rtrim(number_format($m['needed'], 4, '.', ''), '0'), '.') }},
                                già prenotati: {{ rtrim(rtrim(number_format($m['reserved'], 4, '.', ''), '0'), '.') }})
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- 2) Da giacenza libera --}}
            <div>
                <div class="font-semibold mb-1">Copertura da giacenza disponibile</div>

                @php
                    $freeTotal = 0;
                    foreach (($forcePlan['from_free'] ?? []) as $allocs) {
                        foreach ($allocs as $a) { $freeTotal += (float) $a['qty']; }
                    }
                @endphp

                @if(empty($forcePlan['from_free']))
                    <div class="text-gray-500">Nessuna giacenza disponibile utilizzabile.</div>
                @else
                    <div class="text-gray-700 dark:text-gray-200 mb-2">
                        Verranno prenotate da giacenza disponibile:
                        <span class="font-semibold">{{ rtrim(rtrim(number_format($freeTotal, 4, '.', ''), '0'), '.') }}</span>
                        unità complessive.
                    </div>

                    {{-- Dettaglio per componente, senza tecnicismi --}}
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($forcePlan['missing'] as $m)
                            @php
                                $cid = $m['component_id'];
                                $qty = 0;
                                foreach (($forcePlan['from_free'][$cid] ?? []) as $a) { $qty += (float) $a['qty']; }
                            @endphp
                            @if($qty > 0)
                                <li>
                                    <span class="font-semibold">{{ $m['code'] }}</span>
                                    — da giacenza: {{ rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.') }}
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- 3) Riallocazione da ordini penalizzati --}}
            <div>
                <div class="font-semibold mb-1">Riallocazione da altri ordini (ordini penalizzati)</div>

                @if(empty($forcePlan['from_donors']))
                    <div class="text-gray-500">Non è necessario togliere prenotazioni ad altri ordini.</div>
                @else
                    <p class="text-gray-700 dark:text-gray-200 mb-2">
                        Verranno spostate prenotazioni dai seguenti ordini:
                    </p>

                    {{-- Riepilogo per ordine penalizzato --}}
                    @php
                        $donors = collect($forcePlan['from_donors'])
                            ->groupBy('donor_order_id')
                            ->map(function($rows) {
                                $first = $rows->first();
                                $total = $rows->sum('qty');
                                return [
                                    'order_id'      => $first['donor_order_id'],
                                    'delivery_date' => $first['donor_delivery_date'],
                                    'total'         => (float) $total,
                                    'rows'          => $rows,
                                ];
                            })->values();
                    @endphp

                    <ul class="space-y-2">
                        @foreach($donors as $d)
                            <li class="border rounded p-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <span class="font-semibold">Ordine #{{ $d['order_id'] }}</span>
                                        <span class="text-xs text-gray-500">
                                            (consegna: {{ $d['delivery_date'] }})
                                        </span>
                                    </div>
                                    <div>
                                        Totale riallocato:
                                        <span class="font-semibold">{{ rtrim(rtrim(number_format($d['total'], 4, '.', ''), '0'), '.') }}</span>
                                    </div>
                                </div>

                                <div class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                                    Dettaglio componenti:
                                    <ul class="list-disc pl-5 mt-1 space-y-1">
                                        @foreach($d['rows'] as $row)
                                            <li>
                                                <span class="font-semibold">{{ $row['code'] }}</span>
                                                — {{ rtrim(rtrim(number_format($row['qty'], 4, '.', ''), '0'), '.') }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Avviso --}}
            <div class="text-xs text-gray-600 dark:text-gray-300 border-t pt-3">
                <span class="font-semibold">Nota:</span>
                per gli ordini penalizzati verrà generata automaticamente una proposta di approvvigionamento
                per coprire la nuova mancanza.
            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-2 pt-1">
                <button type="button"
                        @click="close"
                        class="px-3 py-1 bg-gray-300 rounded">
                    Annulla
                </button>

                <button type="button"
                        @click="confirm"
                        class="px-3 py-1 bg-red-600 text-white rounded">
                    Conferma e continua
                </button>
            </div>
        </div>
    @else
        <div class="text-sm text-gray-500">
            Nessun piano di riallocazione disponibile.
        </div>

        <div class="flex justify-end gap-2 pt-4">
            <button type="button" @click="close" class="px-3 py-1 bg-gray-300 rounded">Chiudi</button>
        </div>
    @endif
</dialog>

    {{--  Modal Rollback  ----------------------------------------------------}}
    <dialog  wire:ignore
            x-data="rollbackModal()"
            x-init="
                dlg = $el;                                   /* init subito  */
                window.addEventListener('show-rollback-modal', e => open(e.detail))
            "
            @click.outside="close"                           {{-- esc + click out --}}
            @keydown.escape.window="close"
            class="rounded-lg shadow-lg w-96 max-w-full p-5
                bg-white dark:bg-gray-800 backdrop:bg-black/40">

        <h2 class="text-lg font-semibold mb-4">Rollback fase</h2>

        <form @submit.prevent="confirm" class="space-y-4">

            {{-- quantità --}}
            <div>
                <label class="block text-sm mb-1">
                    Quantità (max <span x-text="max"></span>)
                </label>
                <input  type="number" step="1" min="1"
                        x-model.number="qty"
                        class="w-full border rounded px-2 py-1
                            focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            {{-- motivo obbligatorio --}}
            <div>
                <label class="block text-sm mb-1">Motivazione rollback *</label>
                <textarea x-model.trim="reason" rows="2"
                        class="w-full border rounded px-2 py-1
                                focus:ring-indigo-500 focus:border-indigo-500"></textarea>
            </div>

            {{-- flag “riutilizzabile” --}}
            <label class="inline-flex items-center text-sm">
                <input type="checkbox" x-model="reuse"
                    class="mr-2 rounded border-gray-300">
                Componenti riutilizzabili (smontaggio)
            </label>

            <div class="flex justify-end gap-2 text-sm pt-2">
                <button type="button" @click="close"
                        class="px-3 py-1 bg-gray-300 rounded">Annulla</button>
                <button type="submit"
                        class="px-3 py-1 bg-amber-600 text-white rounded">
                    Conferma
                </button>
            </div>
        </form>
    </dialog>

    {{-- Drawer DDT (destra) --}}
    <div
        x-data="{ open: @entangle('ddtDrawerOpen') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50"
        @keydown.escape.window="open=false; $wire.closeDdtDrawer()"
    >
        {{-- overlay --}}
        <div class="absolute inset-0 bg-black/40"
            @click="open=false; $wire.closeDdtDrawer()"></div>

        {{-- pannello --}}
        <div class="absolute top-0 right-0 h-full w-full max-w-md bg-white dark:bg-gray-800 shadow-xl">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold">DDT ordine</div>
                    <div class="text-xs text-gray-500">
                        Nr. Ordine: {{ $ddtDrawerOrderNumber ?? '—' }}
                    </div>
                </div>

                <button type="button"
                        class="px-2 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700"
                        @click="open=false; $wire.closeDdtDrawer()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto" style="height: calc(100% - 64px);">
                @if(empty($ddtDrawerDdts))
                    <div class="text-sm text-gray-500">Nessun DDT trovato per questo ordine.</div>
                @else
                    <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-100 dark:bg-gray-700 uppercase text-xs">
                            <tr>
                                <th class="px-3 py-2 text-left">Nr.</th>
                                <th class="px-3 py-2 text-left">Data</th>
                                <th class="px-3 py-2 text-right">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($ddtDrawerDdts as $d)
                                <tr>
                                    <td class="px-3 py-2 font-semibold">
                                        {{ $d['number'] }}/{{ $d['year'] }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ $d['issued_at'] }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button"
                                                class="inline-flex items-center hover:text-purple-600"
                                                wire:click="printExistingDdt({{ $d['id'] }})">
                                            <i class="fas fa-print mr-1"></i> Stampa
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- Drawer BUONI (destra) --}}
    <div
        x-data="{ open: @entangle('woDrawerOpen') }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50"
        @keydown.escape.window="open=false; $wire.closeWorkOrderDrawer()"
    >
        <div class="absolute inset-0 bg-black/40"
            @click="open=false; $wire.closeWorkOrderDrawer()"></div>

        <div class="absolute top-0 right-0 h-full w-full max-w-md bg-white dark:bg-gray-800 shadow-xl">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold">Buoni ordine</div>
                    <div class="text-xs text-gray-500">
                        Nr. Ordine: {{ $woDrawerOrderNumber ?? '—' }} — Fase: {{ $phase }}
                    </div>
                </div>

                <button type="button"
                        class="px-2 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700"
                        @click="open=false; $wire.closeWorkOrderDrawer()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto" style="height: calc(100% - 64px);">
                @if(empty($woDrawerWorkOrders))
                    <div class="text-sm text-gray-500">Nessun buono trovato per questo ordine in questa fase.</div>
                @else
                    <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-100 dark:bg-gray-700 uppercase text-xs">
                            <tr>
                                <th class="px-3 py-2 text-left">Nr.</th>
                                <th class="px-3 py-2 text-left">Data</th>
                                <th class="px-3 py-2 text-right">Azioni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($woDrawerWorkOrders as $w)
                                <tr>
                                    <td class="px-3 py-2 font-semibold">
                                        {{ $w['number'] }}/{{ $w['year'] }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ $w['issued_at'] }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <button type="button"
                                                class="inline-flex items-center hover:text-purple-600"
                                                wire:click="printExistingWorkOrder({{ $w['id'] }})">
                                            <i class="fas fa-print mr-1"></i> Stampa
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        /**
         * Apre una nuova finestra con la pagina “wrapper” che contiene l’iframe PDF e chiama print().
         */
        window.addEventListener('open-print-window', (e) => {
            const url = e.detail?.url;

            if (!url) return;

            const w = window.open(url, '_blank', 'noopener,noreferrer');

            /* Se il popup blocker interviene */
            if (!w) {
                alert('Popup bloccato: consenti i popup per stampare il DDT.');
            }
        });

        function advanceModal () {
            return {
                dlg : null,          // settato in x-init
                compId : null,
                id  : null,
                max : 0,
                qty : 1,
                operator : '',

                /* ▲ apre il dialog   -------------------------------------- */
                open (p) {
                    this.id  = p.id
                    this.max = p.maxQty
                    this.qty = p.defaultQty
                    this.operator = ''
                    /* fallback per browser senza <dialog> */
                    if (! this.dlg.showModal) {
                        this.dlg.setAttribute('open', '')
                    } else {
                        this.dlg.showModal()
                    }
                },

                /* ▲ conferma → chiama Livewire ----------------------------- */
                confirm () {
                    if (this.qty < 1 || this.qty > this.max) {
                        alert('Quantità non valida. Non può essere maggiore di' + this.max);
                        return;
                    }

                    const comp = Livewire.find(this.compId)
                    if (! comp) { console.error('Livewire component not found'); return }

                    // ①  aggiorna la property
                    comp.set('advQuantity', this.qty)
                        .then(() => comp.set('advOperator', this.operator))
                        // ②  solo dopo chiama il metodo
                        .then(() => comp.call('confirmAdvance', this.qty));

                    this.close();
                },

                /* ▲ chiusura ---------------------------------------------- */
                close () { this.dlg.close ? this.dlg.close() : this.dlg.removeAttribute('open') },
                
                init () {
                    this.dlg    = this.$el
                    this.compId = this.$el.closest('[wire\\:id]').getAttribute('wire:id')  // ② salva id

                    /* fallback browser che non supportano <dialog>  */
                    if (! this.dlg.showModal) {
                        this.dlg.showModal = () => this.dlg.setAttribute('open', '')
                        this.dlg.close     = () => this.dlg.removeAttribute('open')
                    }

                    /* listener all’evento dispatched da Livewire             */
                    window.addEventListener('show-adv-modal', e => this.open(e.detail))
                },
            }
        }

        function rollbackModal () {
            return {
                dlg : null,
                compId : null,    // id Livewire del componente tabella
                id  : null,       // order_item_id
                max : 0,
                qty : 1,
                reason : '',
                reuse  : false,   // flag “componenti riutilizzabili”

                /* ▲ apre il dialog ------------------------------------------------- */
                open (p) {
                    this.id     = p.id
                    this.max    = p.maxQty
                    this.qty    = p.defaultQty
                    this.reason = ''
                    this.reuse  = false

                    if (! this.dlg.showModal) {
                        this.dlg.setAttribute('open', '')
                    } else {
                        this.dlg.showModal()
                    }
                },

                /* ▲ conferma → chiama Livewire ------------------------------------ */
                confirm () {
                    if (this.qty < 1 || this.qty > this.max) {
                        alert('Quantità non valida (1-' + this.max + ').'); return;
                    }
                    if (this.reason.trim() === '') {
                        alert('La motivazione è obbligatoria.'); return;
                    }

                    const comp = Livewire.find(this.compId)
                    if (! comp) { console.error('Livewire component not found'); return }
                            
                    comp.call('confirmRollback', {
                            id:     this.id,
                            qty:    this.qty,
                            max:    this.max,
                            reason: this.reason,
                            reuse:  this.reuse
                        });

                    this.close()
                },

                /* ▲ chiusura ------------------------------------------------------- */
                close () { this.dlg.close ? this.dlg.close() : this.dlg.removeAttribute('open') },

                init () {
                    this.dlg    = this.$el
                    this.compId = this.$el.closest('[wire\\:id]').getAttribute('wire:id')

                    if (! this.dlg.showModal) {           // polyfill <dialog>
                        this.dlg.showModal = () => this.dlg.setAttribute('open', '')
                        this.dlg.close     = () => this.dlg.removeAttribute('open')
                    }
                    window.addEventListener('show-rollback-modal', e => this.open(e.detail))
                },
            }
        }

        function forceReservationModal () {
            return {
                dlg: null,
                compId: null,

                /* ▲ apre il dialog quando Livewire dispatcha l’evento */
                open () {
                    if (! this.dlg.showModal) {
                        this.dlg.setAttribute('open', '')
                    } else {
                        this.dlg.showModal()
                    }
                },

                /* ▲ conferma → chiama Livewire commitForceReservation() */
                confirm () {
                    const comp = Livewire.find(this.compId)
                    if (! comp) { console.error('Livewire component not found'); return }

                    comp.call('commitForceReservation')  // lo implementiamo subito dopo
                    this.close()
                },

                /* ▲ chiusura: chiude dialog e resetta flag in Livewire */
                close () {
                    const comp = Livewire.find(this.compId)
                    if (comp) {
                        comp.set('showForceReservationModal', false)
                    }
                    this.dlg.close ? this.dlg.close() : this.dlg.removeAttribute('open')
                },

                init () {
                    this.dlg    = this.$el
                    this.compId = this.$el.closest('[wire\\:id]').getAttribute('wire:id')

                    /* polyfill per browser senza <dialog> */
                    if (! this.dlg.showModal) {
                        this.dlg.showModal = () => this.dlg.setAttribute('open', '')
                        this.dlg.close     = () => this.dlg.removeAttribute('open')
                    }

                    /* listener evento dispatched da Livewire */
                    window.addEventListener('show-force-reservation-modal', () => this.open())
                },
            }
        }
    </script>
@endpush