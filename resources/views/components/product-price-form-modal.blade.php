{{-- resources/views/components/product-price-form-modal.blade.php --}}
{{-- 
 | Modale: Prezzo Cliente (create/edit con escamotage).
 | - Responsivo: max-h 85vh, header/footer sticky, body scrollabile
 | - Ricerca clienti con debounce + deduplica
 | - Prezzo NETTO (IVA esclusa)
 | PHP/Laravel 12 + Alpine v3
--}}
<div 
    x-show="showCustomerPriceModal" 
    x-cloak 
    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-2 sm:p-4"
    role="dialog" aria-modal="true" aria-labelledby="modal-customer-price-title"
>
    {{-- Overlay --}}
    <div class="absolute inset-0 bg-black/60"></div>

    {{-- Pannello --}}
    <section class="relative z-10 w-full sm:max-w-3xl md:max-w-4xl max-h-[85vh] bg-white dark:bg-gray-800 rounded-lg shadow-xl overflow-hidden flex flex-col">

        {{-- Header sticky --}}
        <header class="px-5 py-3 border-b bg-white dark:bg-gray-800 sticky top-0 z-10">
            <div class="flex items-center justify-between">
                <h3 id="modal-customer-price-title" class="text-lg font-semibold">
                    <span x-text="priceForm.mode==='create' ? 'Prezzo cliente (nuovo)' : 'Prezzo cliente (modifica)'"></span>
                </h3>
                <button @click="closeCustomerPriceModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </header>

        {{-- ===========================================================
             FORM: avvolge body + footer, blocca submit default
           =========================================================== --}}
        <form 
            @submit.prevent="saveCustomerPrice"
            class="flex-1 flex flex-col overflow-hidden" 
            novalidate
        >
            {{-- Body scrollabile --}}
            <div class="px-5 py-4 flex-1 overflow-y-auto">
                {{-- Cliente: ricerca + suggerimenti --}}
                <div class="mb-3">
                    {{-- ======================== CLIENTE (ricerca + selezione) ======================== --}}
                    <div class="relative">
                        {{-- Etichetta visibile solo se NON ho un cliente selezionato --}}
                        <label x-show="!selectedCustomer" x-cloak class="block text-sm font-medium">Cliente</label>

                        {{-- Input ricerca: come nel customer-order-create-modal --}}
                        <input
                            type="text"
                            x-show="!selectedCustomer"
                            x-cloak
                            x-model="customerSearch"
                            @input.debounce.500="searchCustomers"
                            placeholder="Cerca cliente…"
                            class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                                text-sm text-gray-900 dark:text-gray-100"
                            autocomplete="off"
                        >

                        {{-- Dropdown risultati (stessa logica/key dell’altro modale) --}}
                        <div
                            x-show="customerOptions.length"
                            x-cloak
                            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-900 border rounded shadow
                                max-h-40 overflow-y-auto"
                            role="listbox"
                        >
                            <template x-for="(option, idx) in customerOptions" :key="option.id + '-' + idx">
                                <div class="px-2 py-1 hover:bg-gray-200 dark:hover:bg-gray-800 cursor-pointer"
                                    role="option"
                                    @click="selectCustomer(option)">
                                    <span class="text-xs"
                                        x-text="(option.company ?? option.name ?? '—') + ' - ' + (option.shipping_address ?? '')"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Riepilogo cliente selezionato (uguale all’altro modale) --}}
                        <template x-if="selectedCustomer">
                            <div class="mt-2 p-2 border rounded bg-gray-50 dark:bg-gray-700">
                                <p class="font-semibold" x-text="selectedCustomer.company ?? selectedCustomer.name"></p>

                                <template x-if="selectedCustomer.email">
                                    <p class="text-xs" x-text="selectedCustomer.email"></p>
                                </template>

                                <template x-if="selectedCustomer.vat_number">
                                    <p class="text-xs" x-text="'P.IVA: ' + selectedCustomer.vat_number"></p>
                                </template>

                                <template x-if="selectedCustomer.tax_code && !selectedCustomer.vat_number">
                                    <p class="text-xs" x-text="'C.F.: ' + selectedCustomer.tax_code"></p>
                                </template>

                                <template x-if="selectedCustomer.shipping_address">
                                    <p class="text-xs" x-text="selectedCustomer.shipping_address"></p>
                                </template>

                                {{-- Nel mod prezzi: consenti “Cambia” solo in CREATE; in EDIT il cliente è bloccato --}}
                                <button type="button"
                                        x-show="priceForm.mode !== 'edit'"
                                        @click="
                                            selectedCustomer=null;
                                            priceForm.customer_id=null;
                                            customerSearch='';
                                            customerOptions=[]
                                        "
                                        class="text-xs text-red-600 mt-1">
                                    Cambia
                                </button>
                            </div>
                        </template>

                        {{-- errori validazione lato server per il cliente --}}
                        <p class="text-xs text-red-600 mt-1" x-text="priceErrors.customer_id"></p>
                    </div>
                </div>

                {{-- Data di riferimento (per escamotage resolve) --}}
                <div class="mb-3">
                    <label class="block text-sm font-medium mb-1">Data di riferimento</label>
                    <input 
                        type="date" 
                        class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                        x-model="priceForm.reference_date"
                        @change="resolveCustomerPrice"
                    />
                    <p class="text-xs text-gray-500">Usata per capire se esiste una versione valida da modificare.</p>
                </div>

                {{-- Prezzo NETTO --}}
                <div class="mb-3">
                    <label class="block text-sm font-medium mb-1">Prezzo (EUR, netto)</label>
                    <input 
                        type="text" 
                        class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                        placeholder="es. 129,90"
                        x-model="priceForm.price"
                    />
                    <p class="text-xs text-gray-500">IVA esclusa — calcolata in fatturazione.</p>
                    <p class="text-xs text-red-600" x-text="priceErrors.price"></p>
                </div>

                {{-- Validità --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Valido dal</label>
                        <div class="relative">
                            <input 
                                type="date" 
                                class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                                x-model="priceForm.valid_from"
                                @change="onValidFromChanged"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Valido al</label>
                        <div class="relative">
                            <input 
                                type="date" 
                                class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700"
                                x-model="priceForm.valid_to"
                            />
                        </div>
                    </div>
                </div>

                {{-- Auto-chiusura (solo in create) --}}
                <div class="mb-3" x-show="priceForm.mode==='create'">
                    <label class="inline-flex items-center space-x-2">
                        <input type="checkbox" class="rounded border-gray-300 dark:border-gray-700" x-model="priceForm.auto_close_prev">
                        <span class="text-sm">Chiudi automaticamente la versione che copre la data di inizio</span>
                    </label>
                    <p class="text-xs text-gray-500 mt-1">
                        Imposta la versione precedente (se esistente) fino al giorno prima del “Valido dal”.
                    </p>
                </div>

                {{-- Note --}}
                <div class="mb-3">
                    <label class="block text-sm font-medium mb-1">Note</label>
                    <textarea 
                        class="w-full rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700" 
                        rows="2" 
                        x-model="priceForm.notes"
                    ></textarea>
                </div>
            </div>

            {{-- Footer (bottoni) --}}
            <footer class="px-5 py-3 border-t bg-white dark:bg-gray-800 sticky bottom-0 z-10">
                <div class="flex items-center justify-between">
                    <div class="text-xs text-gray-500">
                        <span x-show="priceForm.mode==='edit' && priceForm.current_id">
                            ID versione: <span x-text="priceForm.current_id"></span>
                        </span>
                    </div>
                    <div class="space-x-2">
                        <button type="button" @click="closeCustomerPriceModal" class="px-3 py-1.5 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600">
                            Annulla
                        </button>
                        <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded hover:bg-indigo-500">
                            <i class="fas fa-save mr-1"></i> Salva
                        </button>
                    </div>
                </div>
            </footer>
        </form> {{-- /form --}}

    </section>
</div>
