{{-- resources/views/components/stock-entry-modal.blade.php --}}

<div  class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-y-auto
             max-h-[90vh] w-full p-6 z-10"
      x-data
      x-init="$watch('$store.entryModal.show', v => { if(!v) $el.scrollTop = 0 })"
      x-ref="modalRoot">

    {{-- HEADER --}}
    <div class="flex justify-between items-center mb-4">
        <h3  class="text-lg font-semibold text-gray-900 dark:text-gray-100"
             x-text="$store.entryModal.isNew ? 'Nuovo ricevimento' : 'Registra ricevimento'">
        </h3>
        <button type="button" @click="$store.entryModal.close()"
                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- FORM --}}
    <form  action="{{ route('stock-movements-entry.store') }}"
           method="POST"
           x-ref="entryForm"
           @submit.prevent>
        @csrf

        {{-- Hidden: id ordine (se presente) --}}
        <input type="hidden" name="order_id" x-model="$store.entryModal.formData.order_id">

        <div class="grid gap-4 sm:grid-cols-2">

            {{-- Numero ordine --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">N. Ordine</label>

                <input type="text"
                    x-model="$store.entryModal.formData.order_number"
                    readonly
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-100 dark:bg-gray-700
                            text-sm text-gray-900 dark:text-gray-100">
                {{-- hidden FK --}}
                <input type="hidden" name="order_number_id"
                    x-model="$store.entryModal.formData.order_number_id">
            </div>

            {{-- Numero bolla --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">N. Bolla</label>
                <input  type="text" name="bill_number"
                        x-model="$store.entryModal.formData.bill_number"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
            </div>

            {{-- Data consegna --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Data consegna</label>
                <input  type="date" name="delivery_date"
                        x-model="$store.entryModal.formData.delivery_date"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
            </div>

            {{-- === Fornitore (autocomplete) === --}}
            <div class="relative">
                {{-- Label visibile solo se non c’è un fornitore scelto --}}
                <label  x-show="!$store.entryModal.selectedSupplier" x-cloak
                        class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Fornitore
                </label>

                {{-- Input di ricerca (visibile finché non viene selezionato un fornitore) --}}
                <input  type="text"
                        x-show="!$store.entryModal.selectedSupplier" x-cloak
                        x-model="$store.entryModal.supplierSearch"
                        @input.debounce.500="$store.entryModal.searchSuppliers()"
                        placeholder="Cerca fornitore..."
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">

                {{-- Dropdown risultati, assoluto e scrollabile --}}
                <div  x-show="$store.entryModal.supplierOptions.length" x-cloak
                    class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border rounded shadow
                            max-h-40 overflow-y-auto">
                    <template x-for="option in $store.entryModal.supplierOptions" :key="option.id">
                        <div  class="px-2 py-1 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer"
                            @click="$store.entryModal.selectSupplier(option)">
                            <span x-text="option.name"></span>
                        </div>
                    </template>
                </div>

                {{-- Riepilogo fornitore scelto --}}
                <template x-if="$store.entryModal.selectedSupplier">
                    <div class="mt-2 p-2 border rounded bg-gray-50 dark:bg-gray-700">
                        <p class="font-semibold" x-text="$store.entryModal.selectedSupplier.name"></p>
                        <p class="text-xs"        x-text="$store.entryModal.selectedSupplier.email"></p>
                        <p class="text-xs"        x-text="'P. IVA: ' + $store.entryModal.selectedSupplier.vat_number"></p>
                        <p class="text-xs"
                        x-text="$store.entryModal.selectedSupplier.address.via + ', ' +
                                $store.entryModal.selectedSupplier.address.city + ', ' +
                                $store.entryModal.selectedSupplier.address.postal_code + ', ' +
                                $store.entryModal.selectedSupplier.address.country">
                        </p>
                        <button type="button"
                                @click="$store.entryModal.clearSupplier()"
                                x-show="$store.entryModal.isNew"
                                class="text-xs text-red-600 mt-1">
                            Cambia
                        </button>
                    </div>
                </template>

                {{-- hidden supplier_id per il POST --}}
                <input type="hidden" name="supplier_id" x-model="$store.entryModal.formData.supplier_id">
            </div>
        </div>

        {{-- === Tabella righe ordine === --}}
        <div class="mt-6">
            <table class="min-w-full divide-y divide-gray-200 text-xs">
                <thead class="bg-gray-100 dark:bg-gray-700 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Codice</th>
                        <th class="px-4 py-2 text-left">Componente</th>
                        <th class="px-4 py-2 text-right">Q. ordinata</th>
                        <th class="px-4 py-2 text-right">Q. ricevuta</th>
                        <th class="px-4 py-2 text-left">Unità</th>
                        <th class="px-4 py-2 text-left">Lotto&nbsp;forn.</th>
                        <th class="px-4 py-2 text-left">Lotto&nbsp;int.</th>
                        <th class="px-2 py-2 w-8"></th> {{-- icona --}}
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                    <template x-for="item in $store.entryModal.items" :key="item.id">
                        <tr>
                            <td class="px-4 py-1 whitespace-nowrap" x-text="item.code"></td>
                            <td class="px-4 py-1" x-text="item.name"></td>
                            <td class="px-4 py-1 text-right" x-text="item.qty_ordered"></td>
                            <td class="px-4 py-1 text-right" x-text="item.qty_received ?? '—'"></td>
                            <td class="px-4 py-1" x-text="item.unit"></td>
                            <td class="px-4 py-1" x-text="item.lot_supplier ?? '—'"></td>
                            <td class="px-4 py-1" x-text="item.internal_lot_code ?? '—'"></td>

                            {{-- pulsante “registra riga” – solo icona --}}
                            <td class="px-2 py-1 text-center">
                                <button type="button"
                                        class="text-green-700 hover:text-green-900"
                                        @click="$dispatch('open-row', {itemId: item.id})">
                                    <i class="fas fa-check"></i>
                                </button>
                            </td>
                        </tr>
                    </template>

                    {{-- placeholder se non ci sono righe in create-mode --}}
                    <template x-if="$store.entryModal.isNew && $store.entryModal.items.length === 0">
                        <tr>
                            <td colspan="8" class="px-4 py-4 text-center text-gray-500">
                                Nessuna riga presente. Aggiungila nel prossimo passaggio.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Sezione registrazione riga --}}
        <div class="mt-8 border-t pt-4" x-data>
            <h4 class="font-semibold text-sm mb-3">Registrazione riga</h4>

            <div class="grid gap-4 sm:grid-cols-2">

                {{-- Componente (codice + nome) --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Componente</label>
                    <input  type="text"
                            x-model="$store.entryModal.currentRow.component"
                            :readonly="$store.entryModal.currentRow.id !== null"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>

                {{-- Quantità ordinata --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Q. ordinata</label>
                    <input  type="number"
                            x-model="$store.entryModal.currentRow.qty_ordered"
                            :readonly="$store.entryModal.currentRow.id !== null"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>

                {{-- Quantità ricevuta (sempre editabile) --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Q. ricevuta</label>
                    <input  type="number" min="0" step="0.01"
                            x-model="$store.entryModal.currentRow.qty_received"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>

                {{-- Lotto fornitore (sempre editabile) --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Lotto fornitore</label>
                    <input  type="text"
                            x-model="$store.entryModal.currentRow.lot_supplier"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>

                {{-- Lotto interno (readonly per ora) --}}
                <div class="flex space-x-2 items-end">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Lotto interno</label>
                        <input  type="text"
                                x-model="$store.entryModal.currentRow.internal_lot_code"
                                readonly
                                class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-100 dark:bg-gray-700
                                    text-sm text-gray-900 dark:text-gray-100">
                    </div>

                    {{-- Bottone che chiede il prossimo lotto --}}
                    <button type="button"
                            class="mb-1 px-2 py-1 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-500"
                            @click="$store.entryModal.generateLot()">
                        <i class="fas fa-sync-alt"></i>
                    </button>                    
                </div>
            </div>

            {{-- Pulsante REGISTRA --}}
            <div class="mt-4 flex justify-end">
                <button type="button"
                        class="inline-flex items-center px-3 py-1.5 bg-purple-600 rounded-md text-xs font-semibold
                            text-white uppercase hover:bg-purple-500 focus:outline-none focus:ring-2
                            focus:ring-purple-300 transition"
                        @click="$store.entryModal.saveRow()">
                    <i class="fas fa-save mr-1"></i> Registra
                </button>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="mt-6 flex justify-end space-x-2">
            <button type="button"
                    @click="$store.entryModal.close()"
                    class="inline-flex items-center px-3 py-1.5 border rounded-md text-xs font-semibold
                           text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                Annulla
            </button>
            <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 bg-emerald-600 rounded-md text-xs font-semibold
                           text-white uppercase hover:bg-emerald-500 focus:outline-none focus:ring-2
                           focus:ring-emerald-300 transition"
                    @click="$store.entryModal.saveRegistration()">
                <i class="fas fa-save mr-1"></i> Salva
            </button>
        </div>
    </form>
</div>
