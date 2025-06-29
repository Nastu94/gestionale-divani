{{-- resources/views/components/product-view-modal.blade.php --}}

<div class="fixed inset-0 z-50 flex items-center justify-center" x-cloak>
    {{-- overlay semi-trasparente ------------------------------------ --}}
    <div class="absolute inset-0 bg-black opacity-75"
         @click="showViewModal = false"></div>

    {{-- finestra ----------------------------------------------------- --}}
    <div class="relative z-10 bg-white dark:bg-gray-800 rounded-lg shadow-lg
                w-full max-w-2xl p-6 overflow-y-auto max-h-[90vh]"
         x-trap.noscroll="showViewModal">

        {{-- header --------------------------------------------------- --}}
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Dettaglio prodotto:
                <span x-text="viewProduct.name"></span>
            </h3>

            <button class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    @click="showViewModal = false">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- tabella componenti -------------------------------------- --}}
        <table class="w-full text-sm divide-y divide-gray-300 dark:divide-gray-600">
            <thead class="bg-gray-200 dark:bg-gray-700 uppercase text-left">
                <tr>
                    <th class="px-4 py-2">Codice</th>
                    <th class="px-4 py-2">Descrizione</th>
                    <th class="px-4 py-2 text-right">Quantità</th>
                    <th class="px-4 py-2">UM</th>
                </tr>
            </thead>

            {{-- corpo: righe solo se ci sono componenti --}}
            <tbody x-show="viewProduct.components?.length"
                   class="divide-y divide-gray-200 dark:divide-gray-700">
                <template x-for="comp in viewProduct.components" :key="comp.id">
                    <tr>
                        <td class="px-4 py-2"   x-text="comp.code"></td>
                        <td class="px-4 py-2"   x-text="comp.description"></td>
                        <td class="px-4 py-2 text-right"
                            x-text="comp.pivot.quantity"></td>
                        <td class="px-4 py-2 uppercase tracking-wider"   x-text="comp.unit_of_measure"></td>
                    </tr>
                </template>
            </tbody>

            {{-- messaggio “nessun componente” ------------------------ --}}
            <tfoot x-show="!(viewProduct.components?.length)">
                <tr>
                    <td colspan="4"
                        class="px-4 py-6 text-center italic text-gray-500">
                        Nessun componente associato
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
