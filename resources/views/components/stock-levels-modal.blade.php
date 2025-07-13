{{-- resources/views/components/stock-levels-modal.blade.php --}}
<div
    x-show="showStockModal"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
>
    <div class="absolute inset-0"
         @click="showStockModal = false"></div>

    <div class="relative z-10 w-full max-w-4xl
                bg-white rounded-lg shadow-lg p-6 overflow-y-auto max-h-[90vh] dark:text-gray-100">

        {{-- header --}}
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Giacenze componente <span x-text="stockModalTitle"></span>
            </h3>
            <button @click="showStockModal = false"
                    class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- tabella --}}
        <div class="overflow-x-auto">
            <table class="min-w-full table-auto text-sm divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-300 dark:bg-gray-700 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-2 text-left">Codice</th>
                    <th class="px-4 py-2 text-left">Descrizione</th>
                    <th class="px-4 py-2 text-left">U.M.</th>
                    <th class="px-4 py-2 text-left">Magazzino</th>
                    <th class="px-4 py-2 text-left">Lotto interno</th>
                    <th class="px-4 py-2 text-right">Disponibile</th>
                </tr>
                </thead>

                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <!-- usa indice come fallback e una chiave composita per evitare duplicati -->
                    <template
                        x-for="(row, idx) in stockRows"
                        :key="`${row.id ?? 'row'}-${row.warehouse_code}-${row.internal_lot ?? ''}-${idx}`"
                    >
                        <tr>
                            <td class="px-4 py-1 whitespace-nowrap" x-text="row.component_code"></td>
                            <td class="px-4 py-1" x-text="row.component_desc"></td>
                            <td class="px-4 py-1 whitespace-nowrap uppercase" x-text="row.uom"></td>
                            <td class="px-4 py-1 whitespace-nowrap" x-text="row.warehouse_code"></td>
                            <td class="px-4 py-1 whitespace-nowrap text-center" x-text="row.internal_lot || '—'"></td>
                            <td class="px-4 py-1 text-right" x-text="row.qty"></td>
                        </tr>
                    </template>

                    <template x-if="stockRows.length === 0">
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                Nessuna giacenza da mostrare
                            </td>
                        </tr>
                    </template>
                </tbody>

            </table>
        </div>
    </div>
</div>
