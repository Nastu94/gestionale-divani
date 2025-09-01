{{-- resources/views/components/product-pricelist-modal.blade.php --}}
{{-- 
 | Modale: Listino Prodotto–Cliente (solo consultazione).
 | - Responsivo: max-h 85vh, body scrollabile
 | - Overlay cliccabile per chiudere
--}}
<div 
    x-show="showPriceListModal" 
    x-cloak 
    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-2 sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="modal-pricelist-title"
>
    {{-- Overlay cliccabile per chiudere --}}
    <div class="absolute inset-0 bg-black/60"></div>

    {{-- Pannello --}}
    <section class="relative z-10 w-full sm:max-w-4xl max-h-[85vh] bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden flex flex-col">

        {{-- Header sticky --}}
        <header class="px-5 py-3 border-b bg-white dark:bg-gray-800 sticky top-0 z-10">
            <div class="flex items-center justify-between">
                <h3 id="modal-pricelist-title" class="text-lg font-semibold">Listino Prodotto–Cliente</h3>
                <button @click="showPriceListModal=false" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </header>

        {{-- Body scrollabile (tabella) --}}
        <div class="px-5 py-4 flex-1 overflow-y-auto">
            <div class="overflow-x-auto">
                <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr class="uppercase tracking-wider">
                            <th class="px-4 py-2 text-left">Cliente</th>
                            <th class="px-4 py-2 text-left">Prezzo (EUR, netto)</th>
                            <th class="px-4 py-2 text-left">Validità</th>
                            <th class="px-4 py-2 text-left">Note</th>
                            <th class="px-4 py-2 text-left">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <template x-for="row in priceList" :key="row.id">
                            <tr>
                                <td class="px-4 py-2" x-text="row.customer?.company ?? '—'"></td>
                                <td class="px-4 py-2" x-text="Number(row.price).toFixed(2)"></td>
                                <td class="px-4 py-2">
                                    <span x-text="row.valid_from ?? '—'"></span>
                                    <span>→</span>
                                    <span x-text="row.valid_to ?? '—'"></span>
                                    <span class="ml-2 px-2 py-0.5 rounded text-xs"
                                        :class="{
                                            'bg-green-100 text-green-800': row.status==='corrente',
                                            'bg-blue-100 text-blue-800': row.status==='futuro',
                                            'bg-gray-200 text-gray-800': row.status==='passato'
                                        }"
                                        x-text="row.status">
                                    </span>
                                </td>
                                <td class="px-4 py-2" x-text="row.notes ?? '—'"></td>
                                <td class="px-4 py-2">
                                    @can('product-prices.delete')
                                    <button class="text-red-600 hover:text-red-800"
                                            @click="deleteCustomerPrice(row)">
                                        <i class="fas fa-trash-alt mr-1"></i> Elimina
                                    </button>
                                    @endcan
                                </td>
                            </tr>
                        </template>
                        <tr x-show="priceList.length===0">
                            <td colspan="5" class="px-4 py-3 text-center text-gray-500">Nessun prezzo registrato.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Footer sticky --}}
        <footer class="px-5 py-3 border-t bg-white dark:bg-gray-800 sticky bottom-0 z-10">
            <div class="text-right">
                <button @click="showPriceListModal=false" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600">Chiudi</button>
            </div>
        </footer>
    </section>
</div>
