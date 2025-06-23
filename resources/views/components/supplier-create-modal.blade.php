{{-- resources/views/components/supplier-create-modal.blade.php --}}


@props(['suppliers'])

{{-- 
    Componente Create/Edit Fornitore 
    Props:
      - suppliers: collection di Supplier (usata in modalità 'edit' per popolare il form)
--}}

<div 
    @click.away="showModal = false" 
    class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-y-auto max-h-[90vh] w-full max-w-3xl p-6 z-10"
>
    {{-- Header del modal --}}
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            <span x-text="mode === 'create' ? 'Nuovo Fornitore' : 'Modifica Fornitore'"></span>
        </h3>
        <button type="button" @click="showModal = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Form di creazione/modifica --}}
    <form 
        x-bind:action="mode === 'create' ? '{{ route('suppliers.store') }}' : '{{ url('suppliers') }}/' + form.id" 
        method="POST" 
        @submit.prevent="if(validateSupplier()) $el.submit()"
    >
        @csrf

        {{-- Se siamo in edit, aggiungiamo il metodo PUT --}}
        <template x-if="mode === 'edit'">
            <input type="hidden" name="_method" value="PUT" />
        </template>

        <div class="space-y-4">
            {{-- Campo: Nome --}}
            {{-- Nome o ragione sociale --}}
            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nome / Ragione Sociale</label>
                <input
                    id="name"
                    name="name"
                    x-model="form.name"
                    type="text" required
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                />
                <p x-text="errors.name?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Campo: Partita IVA --}}
            <div>
                <label for="vat_number" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Partita IVA</label>
                <input 
                    id="vat_number" 
                    name="vat_number" 
                    x-model="form.vat_number" 
                    type="text"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />
                <p x-text="errors.vat_number?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Campo: Codice Fiscale --}}
            <div>
                <label for="tax_code" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Codice Fiscale</label>
                <input 
                    id="tax_code" 
                    name="tax_code" 
                    x-model="form.tax_code" 
                    type="text"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />
                <p x-text="errors.tax_code?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Campo: Email --}}
            <div>
                <label for="email" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input 
                    id="email" 
                    name="email" 
                    x-model="form.email" 
                    type="email" 
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />
                
                <p x-text="errors.email?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Campo: Telefono --}}
            <div>
                <label for="phone" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Telefono</label>
                <input 
                    id="phone" 
                    name="phone" 
                    x-model="form.phone" 
                    type="text" 
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />                
                <p x-text="errors.phone?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Campo: Sito Web --}}
            <div>
                <label for="website" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Sito Web</label>
                <input 
                    id="website" 
                    name="website" 
                    x-model="form.website" 
                    type="url" 
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />
                <p x-text="errors.website?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Campo: Termini di pagamento --}}
            <div>
                <label for="payment_terms" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Termini di pagamento</label>
                <textarea 
                    id="payment_terms" 
                    name="payment_terms" 
                    x-model="form.payment_terms" 
                    rows="3"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                ></textarea>
                <p x-text="errors.payment_terms?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Sezione Indirizzo Fornitore --}}
            <div class="grid grid-cols-2 gap-4">
                {{-- Via --}}
                <div>
                    <label for="address_via" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Via</label>
                    <input
                        id="address_via"
                        name="address[via]"
                        x-model="form.address.via"
                        type="text"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                    />
                    <p x-text="errors['address.via'] ? errors['address.via'][0] : ''" 
                        class="text-red-600 text-xs mt-1"></p>
                </div>

                {{-- Città --}}
                <div>
                    <label for="address_city" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Città</label>
                    <input
                        id="address_city"
                        name="address[city]"
                        x-model="form.address.city"
                        type="text"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                    />
                    <p x-text="errors['address.city'] ? errors['address.city'][0] : ''" 
                        class="text-red-600 text-xs mt-1"></p>
                </div>

                {{-- CAP --}}
                <div>
                    <label for="address_postal_code" class="block text-xs font-medium text-gray-700 dark:text-gray-300">CAP</label>
                    <input
                        id="address_postal_code"
                        name="address[postal_code]"
                        x-model="form.address.postal_code"
                        type="text"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                    />
                    <p x-text="errors['address.postal_code'] ? errors['address.postal_code'][0] : ''" 
                        class="text-red-600 text-xs mt-1"></p>
                </div>

                {{-- Nazione --}}
                <div>
                    <label for="address_country" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nazione</label>
                    <input
                        id="address_country"
                        name="address[country]"
                        x-model="form.address.country"
                        type="text"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                    />
                    <p x-text="errors['address.country'] ? errors['address.country'][0] : ''" 
                        class="text-red-600 text-xs mt-1"></p>
                </div>
            </div>

            {{-- Campo: Attivo/Inattivo --}}
            <div class="flex items-center">
                <input 
                    id="is_active" 
                    name="is_active" 
                    x-model="form.is_active" 
                    type="checkbox" 
                    class="h-4 w-4 text-purple-600 border-gray-300 rounded"
                />
                <label for="is_active" class="ml-2 block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Fornitore attivo
                </label>
            </div>
        </div>

        {{-- Azioni del modal --}}
        <div class="mt-6 flex justify-end space-x-2">
            <button 
                type="button" 
                @click="showModal = false"
                class="px-4 py-1.5 text-xs font-medium rounded-md bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-500"
            >
                Annulla
            </button>
            <button 
                type="submit" 
                class="px-4 py-1.5 text-xs font-medium rounded-md bg-purple-600 text-white hover:bg-purple-500"
            >
                <span x-text="mode === 'create' ? 'Salva' : 'Aggiorna'"></span>
            </button>
        </div>
    </form>
</div>
