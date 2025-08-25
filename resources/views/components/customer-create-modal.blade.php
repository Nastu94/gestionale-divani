{{-- resources/views/components/customer-create-modal.blade.php --}}

@props(['customers'])

{{-- 
    Componente Create/Edit Cliente 
    Props:
      - customers: collection di Customer (usata in modalità 'edit' per popolare il form)
--}}

<div 
    class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-y-auto max-h-[90vh] w-full max-w-3xl p-6 z-10"
>
    {{-- Header del modal --}}
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            <span x-text="mode === 'create' ? 'Nuovo Cliente' : 'Modifica Cliente'"></span>
        </h3>
        <button type="button" @click="showModal = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Form di creazione/modifica --}}
    <form 
        x-bind:action="mode === 'create' ? '{{ route('customers.store') }}' : '{{ url('customers') }}/' + form.id" 
        method="POST" 
        @submit.prevent="if(validateCustomer()) $el.submit()"
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
                <label for="company" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nome / Ragione Sociale</label>
                <input
                    id="company"
                    name="company"
                    x-model="form.company"
                    type="text" required
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                />
                @error('company')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
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
                @error('vat_number')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
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
                @error('tax_code')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
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
                @error('email')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
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
                @error('phone')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
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
                    Cliente attivo
                </label>
            </div>

            <hr class="my-4 border-gray-300 dark:border-gray-600" />

            {{-- Sezione Dinamica: Indirizzi Cliente --}}
            <div>
                <div class="flex justify-between items-center mb-2">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Indirizzi</h4>
                    <button 
                        type="button" 
                        @click="form.addresses.push({ type: 'billing', address: '', city: '', postal_code: '', country: 'Italia' })"
                        class="inline-flex items-center px-2 py-1 bg-green-600 rounded text-xs font-semibold text-white hover:bg-green-500 focus:outline-none"
                    >
                        <i class="fas fa-plus mr-1"></i> Aggiungi indirizzo
                    </button>
                </div>

                {{-- Template per ogni indirizzo --}}
                <template x-for="(addr, idx) in form.addresses" :key="idx">
                    <div class="border rounded-md p-3 mb-3 bg-gray-50 dark:bg-gray-700 relative">
                        {{-- Pulsante Rimuovi --}}
                        <button 
                            type="button" 
                            @click="form.addresses.splice(idx, 1)" 
                            class="absolute top-2 right-2 text-red-500 hover:text-red-700"
                        >
                            <i class="fas fa-times-circle"></i>
                        </button>

                        {{-- Tipo indirizzo --}}
                        <div class="mb-2">
                            <label for="addresses[${idx}][type]" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                            <select 
                                :id="`addresses[${idx}][type]`"
                                :name="`addresses[${idx}][type]`" 
                                x-model="addr.type" 
                                class="mt-1 block w-full px-3 py-2 border rounded-md bg-white dark:bg-gray-600 text-sm text-gray-900 dark:text-gray-100"
                            >
                                <option value="billing">Fatturazione</option>
                                <option value="shipping">Spedizione</option>
                                <option value="other">Altro</option>
                            </select>
                        </div>

                        {{-- Via/Indirizzo --}}
                        <div class="mb-2">
                            <label for="`addresses[${idx}][address]`" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Indirizzo</label>
                            <input 
                                :id="`addresses[${idx}][address]`"
                                type="text" 
                                :name="`addresses[${idx}][address]`" 
                                x-model="addr.address" 
                                class="mt-1 block w-full px-3 py-2 border rounded-md bg-white dark:bg-gray-600 text-sm text-gray-900 dark:text-gray-100"
                            />
                        </div>

                        {{-- Città, CAP, Nazione --}}
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label for="`addresses[${idx}][city]`" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Città</label>
                                <input 
                                    type="text" 
                                    :id="`addresses[${idx}][city]`"
                                    :name="`addresses[${idx}][city]`" 
                                    x-model="addr.city" 
                                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-white dark:bg-gray-600 text-sm text-gray-900 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label for="`addresses[${idx}][postal_code]`" class="block text-xs font-medium text-gray-700 dark:text-gray-300">CAP</label>
                                <input 
                                    type="text" 
                                    :id="`addresses[${idx}][postal_code]`"
                                    :name="`addresses[${idx}][postal_code]`" 
                                    x-model="addr.postal_code" 
                                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-white dark:bg-gray-600 text-sm text-gray-900 dark:text-gray-100"
                                />
                            </div>
                            <div>
                                <label for="`addresses[${idx}][country]`" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nazione</label>
                                <input 
                                    type="text" 
                                    :id="`addresses[${idx}][country]`"
                                    :name="`addresses[${idx}][country]`" 
                                    x-model="addr.country" 
                                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-white dark:bg-gray-600 text-sm text-gray-900 dark:text-gray-100"
                                />
                            </div>
                        </div>
                    </div>
                </template>
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
