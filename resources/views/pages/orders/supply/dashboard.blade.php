{{-- resources/views/pages/orders/supply/dashboard.blade.php --}}

<x-app-layout>
    {{-- Header pagina --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Supply – Dashboard
            </h2>

            {{-- Badge DRY-RUN, se attivo --}}
            @if($cfg['dry_run'])
                <span class="inline-flex items-center rounded-full bg-yellow-100 px-3 py-1 text-xs font-medium text-yellow-800">
                    DRY-RUN ATTIVO
                </span>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Prossimo run + Azione --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <div class="text-sm text-gray-500">Prossimo run pianificato</div>
                            <div class="font-semibold">
                                {{ $nextRun->isoFormat('dddd D MMMM YYYY, HH:mm') }}
                                <span class="text-gray-400">({{ $cfg['schedule_timezone'] }})</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            @if($hasOpenRun)
                                <span class="text-sm text-amber-700 bg-amber-100 px-3 py-1 rounded">
                                    Run in corso: attendi il completamento
                                </span>
                            @endif

                            <button
                                type="button"
                                @click="document.getElementById('forceRunForm').classList.toggle('hidden')"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                                @if($hasOpenRun) disabled @endif
                            >
                                Esegui adesso
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form "Esegui adesso" (toggle) --}}
            <div id="forceRunForm" class="bg-white overflow-hidden shadow-sm sm:rounded-lg hidden">
                <div class="p-6">
                    <form method="POST" action="{{ route('orders.supply.run') }}" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @csrf

                        {{-- Finestra in giorni --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Finestra (giorni)</label>
                            <input type="number" name="days" min="1" max="60"
                                   value="{{ old('days', $cfg['window_days']) }}"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">Default da config.</p>
                        </div>

                        {{-- Dry-run --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Dry-run</label>
                            <label class="mt-2 inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="dry_run" value="1"
                                       @checked(old('dry_run', $cfg['dry_run'])) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700">Non scrivere su DB</span>
                            </label>
                        </div>

                        <div class="md:flex md:items-end md:justify-end">
                            <button
                                class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700"
                                @if($hasOpenRun) disabled @endif
                            >
                                Avvia riconciliazione ora
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Config attive --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="text-sm text-gray-500 mb-2">Config attive</div>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div><dt class="text-gray-400 text-xs">Finestra (giorni)</dt><dd class="font-semibold">{{ $cfg['window_days'] }}</dd></div>
                        <div><dt class="text-gray-400 text-xs">Retention max runs</dt><dd class="font-semibold">{{ $cfg['retention_max'] }}</dd></div>
                        <div><dt class="text-gray-400 text-xs">Orario esecuzione</dt><dd class="font-semibold">{{ $cfg['schedule_time'] }}</dd></div>
                        <div><dt class="text-gray-400 text-xs">Fuso orario</dt><dd class="font-semibold">{{ $cfg['schedule_timezone'] }}</dd></div>
                        <div class="sm:col-span-4"><dt class="text-gray-400 text-xs">Dry run</dt><dd class="font-semibold">{{ $cfg['dry_run'] ? 'true' : 'false' }}</dd></div>
                    </dl>
                </div>
            </div>

            {{-- Flash messages --}}
            @if(session('status'))
                <div class="bg-emerald-50 border-l-4 border-emerald-400 p-4 rounded">
                    <div class="text-sm text-emerald-800">{{ session('status') }}</div>
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded">
                    <div class="text-sm text-red-800">{{ session('error') }}</div>
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded space-y-1">
                    @foreach($errors->all() as $err)
                        <div class="text-sm text-red-800">{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            {{-- KPI cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500">Run (ultimi {{ $kpi['runs_total'] }})</div>
                        <div class="mt-1 font-bold">{{ $kpi['runs_ok'] }} OK / {{ $kpi['runs_error'] }} ERROR</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500">Ordini toccati (ultimi {{ $kpi['runs_total'] }})</div>
                        <div class="mt-1 font-bold">
                            {{ $kpi['orders_touched_sum'] }}
                            <span class="text-gray-400 font-normal"> (avg {{ $kpi['orders_touched_avg'] }})</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500">Qty coperte (stock + PO)</div>
                        <div class="mt-1 font-bold">{{ number_format((float) $kpi['covered_qty'], 0, ',', '.') }}</div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-500">PO creati</div>
                        <div class="mt-1 font-bold">{{ (int) $kpi['po_created'] }}</div>

                        @if($lastRunPoNums->isNotEmpty())
                            <div class="mt-2 text-xs text-gray-500">Ultimo run:</div>
                            <div class="mt-1 flex flex-wrap gap-2">
                                @foreach($lastRunPoNums as $po)
                                    <span class="inline-flex items-center gap-2 rounded bg-gray-100 px-2 py-1 text-xs text-gray-800">
                                        #{{ $po['id'] }} @if($po['number']) ({{ $po['number'] }}) @endif
                                        <button type="button" class="text-indigo-600 hover:underline" onclick="copyText('{{ $po['id'] }}')">copia</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tabella run --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">ID</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Week</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Finestra</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Durata</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Esito</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Scan</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Skip</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Touch</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Stock (lines/qty)</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">PO (lines/qty)</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">Short (comp/qty)</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-500">PO creati</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($runs as $r)
                                <tr @class(['bg-red-50' => $r->result === 'error'])>
                                    <td class="px-3 py-2">{{ $r->id }}</td>
                                    <td class="px-3 py-2">{{ $r->week_label }}</td>

                                    <td class="px-3 py-2">
                                        {{ \Illuminate\Support\Str::before($r->window_start, ' ') }}
                                        →
                                        {{ \Illuminate\Support\Str::before($r->window_end, ' ') }}
                                    </td>

                                    {{-- Durata: ms interi con separatore migliaia --}}
                                    <td class="px-3 py-2">{{ number_format((float) $r->duration_ms, 0, ',', '.') }} ms</td>

                                    <td class="px-3 py-2">
                                        <span @class([
                                            'px-2 py-0.5 rounded text-xs font-semibold',
                                            'bg-green-100 text-green-800' => $r->result === 'ok',
                                            'bg-yellow-100 text-yellow-800' => $r->result === 'partial',
                                            'bg-red-100 text-red-800' => $r->result === 'error',
                                        ])>
                                            {{ strtoupper($r->result) }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-2">{{ (int) $r->orders_scanned }}</td>
                                    <td class="px-3 py-2">{{ (int) $r->orders_skipped_fully_covered }}</td>
                                    <td class="px-3 py-2">{{ (int) $r->orders_touched }}</td>

                                    <td class="px-3 py-2">
                                        {{ (int) $r->stock_reservation_lines }} /
                                        {{ number_format((float) $r->stock_reserved_qty, 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ (int) $r->po_reservation_lines }} /
                                        {{ number_format((float) $r->po_reserved_qty, 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ (int) $r->components_in_shortfall }} /
                                        {{ number_format((float) $r->shortfall_total_qty, 0, ',', '.') }}
                                    </td>

                                    <td class="px-3 py-2 flex">
                                        {{ (int) $r->purchase_orders_created }}
                                        @if(!empty($r->created_po_ids))
                                            <details class="mt-1 ml-2">
                                                <summary class="cursor-pointer text-indigo-600 hover:underline text-xs">vedi</summary>
                                                <div class="mt-1 flex flex-wrap gap-2">
                                                    @foreach($r->created_po_ids as $pid)
                                                        <span class="inline-flex items-center gap-2 rounded bg-gray-100 px-2 py-1 text-xs text-gray-800">
                                                            #{{ $pid }}
                                                            <button type="button" class="text-indigo-600 hover:underline" onclick="copyText('{{ $pid }}')"><i class="fas fa-copy"></i></button>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Dettaglio errore, se presente --}}
                                @if($r->result === 'error' && !empty($r->error_context))
                                    <tr>
                                        <td colspan="13">
                                            <pre class="mt-2 text-xs bg-red-50 p-3 rounded overflow-auto">{{ json_encode($r->error_context, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="13" class="px-3 py-6 text-center text-gray-400">Nessun run presente.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    {{-- Pagination --}}
                    <div class="mt-4">
                        {{ $runs->links() }}
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- utilità: copia negli appunti (usata per gli ID PO) --}}
    <script>
        function copyText(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text);
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch(e) {}
                document.body.removeChild(ta);
            }
        }
    </script>
</x-app-layout>
