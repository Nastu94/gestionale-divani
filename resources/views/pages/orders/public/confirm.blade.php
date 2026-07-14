<x-guest-layout>
    <div class="max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">
        
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                {{ __('Conferma Ordine') }}
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ config('app.name') }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-700">
            
            @if(session('success'))
                <div class="p-4 bg-green-50 dark:bg-green-900 border-l-4 border-green-500">
                    <p class="text-sm font-medium text-green-800 dark:text-green-100">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 bg-red-50 dark:bg-red-900 border-l-4 border-red-500">
                    <p class="text-sm font-medium text-red-800 dark:text-red-100">{{ session('error') }}</p>
                </div>
            @endif

            @if($expired || !$order)
                <div class="p-10 text-center">
                    <i class="fas fa-exclamation-circle text-6xl text-rose-500 mb-6 drop-shadow-md"></i>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                        @lang('orders.confirm.link_expired')
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 max-w-md mx-auto">
                        @lang('orders.confirm.link_help', ['days' => $ttl_days])
                    </p>
                </div>
            @else
                
                {{-- Dettagli Header --}}
                <div class="p-6 sm:p-8 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col md:flex-row md:justify-between items-start md:items-center gap-4">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                @lang('orders.confirm.subtitle', ['order' => $order->orderNumber->full ?? ('#'.$order->id)])
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Effettuato il: {{ \Carbon\Carbon::parse($order->ordered_at)->format('d/m/Y') }}
                            </p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 px-4 py-2 rounded-lg shadow-sm border border-gray-200 dark:border-gray-600 text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Consegna Prevista</p>
                            <p class="text-lg font-bold text-indigo-600 dark:text-indigo-400">
                                {{ $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('d/m/Y') : 'Da definire' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Dati Cliente e Indirizzo --}}
                <div class="p-6 sm:p-8 border-b border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-3 flex items-center gap-2">
                                <i class="fas fa-user text-gray-400"></i> Dettagli Cliente
                            </h3>
                            <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                                <p><span class="font-medium text-gray-900 dark:text-gray-100">Azienda:</span> {{ $order->customer->company ?? 'N/D' }}</p>
                                <p><span class="font-medium text-gray-900 dark:text-gray-100">Email:</span> {{ $order->customer->email ?? 'N/D' }}</p>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-3 flex items-center gap-2">
                                <i class="fas fa-truck text-gray-400"></i> Indirizzo di Consegna
                            </h3>
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                <p class="leading-relaxed">{{ $order->shipping_address ?? 'Indirizzo non specificato' }}</p>
                                @if($order->shipping_zone)
                                    <p class="mt-2"><span class="font-medium text-gray-900 dark:text-gray-100">Zona:</span> {{ $order->shipping_zone }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Prodotti --}}
                <div class="p-6 sm:p-8">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i class="fas fa-box-open text-gray-400"></i> Riepilogo Articoli
                    </h3>
                    
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-600">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold text-gray-900 dark:text-gray-200 sm:pl-6">Prodotto</th>
                                    <th scope="col" class="px-3 py-3.5 text-right text-xs font-semibold text-gray-900 dark:text-gray-200">Q.tà</th>
                                    <th scope="col" class="px-3 py-3.5 text-right text-xs font-semibold text-gray-900 dark:text-gray-200">Prezzo</th>
                                    <th scope="col" class="py-3.5 pl-3 pr-4 text-right text-xs font-semibold text-gray-900 dark:text-gray-200 sm:pr-6">Totale</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                @foreach($order->items as $it)
                                    @php
                                        $var = $it->variable;
                                        $fabric = $var->fabric?->name ?? '';
                                        $color  = $var->color?->name ?? '';
                                        $cNote  = $var->color_notes ?? '';
                                        $details = [];
                                        if ($fabric) $details[] = $fabric;
                                        if ($color) $details[] = $color;
                                        if ($cNote) $details[] = $cNote;
                                        $detailsStr = implode(' - ', $details);
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                        <td class="py-4 pl-4 pr-3 text-sm sm:pl-6">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $it->product->name ?? 'N/D' }}</div>
                                            <div class="text-gray-500 dark:text-gray-400 mt-1 flex flex-col gap-1">
                                                <span><i class="fas fa-barcode text-gray-400 text-xs w-4"></i> {{ $it->product->sku ?? 'N/D' }}</span>
                                                @if($detailsStr)
                                                    <span><i class="fas fa-palette text-gray-400 text-xs w-4"></i> {{ $detailsStr }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-300 text-right whitespace-nowrap">
                                            {{ $it->quantity }}
                                        </td>
                                        <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-300 text-right whitespace-nowrap">
                                            € {{ number_format($it->unit_price, 2, ',', '.') }}
                                        </td>
                                        <td class="py-4 pl-3 pr-4 text-sm font-bold text-gray-900 dark:text-white text-right whitespace-nowrap sm:pr-6">
                                            € {{ number_format($it->quantity * $it->unit_price, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th scope="row" colspan="3" class="hidden pl-4 pr-3 py-4 text-right text-base font-bold text-gray-900 dark:text-white sm:table-cell sm:pl-6">Totale Ordine</th>
                                    <th scope="row" class="pl-4 pr-3 py-4 text-left text-base font-bold text-gray-900 dark:text-white sm:hidden">Totale</th>
                                    <td class="pl-3 pr-4 py-4 text-right text-lg font-bold text-indigo-600 dark:text-indigo-400 sm:pr-6">
                                        € {{ number_format($order->total, 2, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Azioni (Accetta / Rifiuta) --}}
                <div class="p-6 sm:p-8 bg-gray-100 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700" x-data="{ showReject: false }">
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center mb-6">
                        Confermando l'ordine, accetti tutte le condizioni di fornitura ed i dettagli riportati sopra.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                        {{-- Pulsante Accetta --}}
                        <form method="POST" action="{{ route('orders.customer.confirm.accept', $token) }}" class="w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center px-8 py-3 border border-transparent shadow-md text-base font-bold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all transform hover:-translate-y-0.5">
                                <i class="fas fa-check-circle mr-2 text-xl"></i> @lang('orders.confirm.btn_confirm')
                            </button>
                        </form>

                        {{-- Bottone Mostra Modulo Rifiuto --}}
                        <button type="button" @click="showReject = !showReject" class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-gray-300 dark:border-gray-600 shadow-sm text-base font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all">
                            <i class="fas fa-times-circle mr-2"></i> Hai riscontrato un problema?
                        </button>
                    </div>

                    {{-- Modulo Rifiuto (Nascosto di default) --}}
                    <div x-show="showReject" x-transition.opacity.duration.300ms class="mt-6 p-6 bg-white dark:bg-gray-800 border border-rose-200 dark:border-rose-900 rounded-xl shadow-inner" style="display: none;">
                        <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-2 text-center">Vuoi rifiutare l'ordine?</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 text-center">Ti preghiamo di indicarci il motivo per aiutarci a correggere il problema.</p>
                        <form method="POST" action="{{ route('orders.customer.confirm.reject', $token) }}" class="flex flex-col sm:flex-row gap-3">
                            @csrf
                            <input type="text" name="reason" required class="flex-grow shadow-sm focus:ring-rose-500 focus:border-rose-500 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md p-3"
                                placeholder="Esempio: Indirizzo errato, prezzi non concordati..." minlength="3" maxlength="1000">
                            <button type="submit" class="inline-flex justify-center items-center px-6 py-3 border border-transparent shadow-sm text-sm font-bold rounded-md text-white bg-rose-600 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500 transition-colors">
                                @lang('orders.confirm.btn_reject')
                            </button>
                        </form>
                    </div>
                </div>

            @endif

        </div>
        
        <div class="text-center mt-8">
            <p class="text-xs text-gray-400 dark:text-gray-500">
                &copy; {{ date('Y') }} {{ config('app.name') }}. Tutti i diritti riservati.
            </p>
        </div>
    </div>
</x-guest-layout>
