{{-- resources/views/components/product-create-modal.blade.php --}}

@props(['products', 'components'])

{{--
    Componente Create/Edit Prodotto
    Props:
      - products: collection di Product (usata in 'edit' per popolare il form)
      - components: collection di Component (per selezionare i componenti del prodotto)
--}}

<div
    @click.away="showModal = false"
    class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-y-auto max-h-[90vh] w-full max-w-3xl p-6 z-10"
>
    {{-- Header del modal --}}
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            <span x-text="mode === 'create' ? 'Nuovo Prodotto' : 'Modifica Prodotto'"></span>
        </h3>
        <button type="button" @click="showModal = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Form di creazione/modifica --}}
    <form
        x-bind:action="mode === 'create'
            ? '{{ route('products.store') }}'
            : '{{ url('products') }}/' + form.id"
        method="POST"
        @submit.prevent="if(validateProduct()) $el.submit()"
    >
        @csrf
        <template x-if="mode === 'edit'">
            <input type="hidden" name="_method" value="PUT" />
        </template>

        <div class="space-y-4">
            {{-- Nome Prodotto --}}
            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nome Prodotto</label>
                <input
                    id="name"
                    name="name"
                    x-model="form.name"
                    type="text" required
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                />
                <p x-text="errors.name?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Codice Prodotto --}}
            <div class="flex items-end space-x-2">
                <div class="flex-1">
                    <label for="sku" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Codice Prodotto</label>
                    <input
                        id="sku"
                        name="sku"
                        x-model="form.sku"
                        type="text"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                        readonly
                        required
                    />
                    <p x-text="errors.sku?.[0]" class="text-red-600 text-xs mt-1"></p>
                </div>
                <button
                    type="button"
                    @click="generateCode()"
                    class="inline-flex items-center px-2 py-3 mb-1 bg-indigo-600 rounded text-xs font-semibold text-white hover:bg-indigo-500 focus:outline-none"
                >
                    <i class="fas fa-sync-alt mr-1"></i> Genera Codice
                </button>
            </div>

            {{-- Descrizione Prodotto --}}
            <div>
                <label for="description" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Descrizione</label>
                <textarea
                    id="description"
                    name="description"
                    x-model="form.description"
                    rows="3"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                ></textarea>
                <p x-text="errors.description?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Prezzo --}}
            <div>
                <label for="price" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Prezzo (&euro;)</label>
                <input
                    id="price"
                    name="price"
                    x-model="form.price"
                    type="number" step="0.01" required
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                />
                <p x-text="errors.price?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            <hr class="my-4 border-gray-300 dark:border-gray-600" />

            {{-- Stato Prodotto --}}
            <div class="flex items-center space-x-2">
                <input
                    id="is_active"
                    name="is_active"
                    type="checkbox"
                    x-model="form.is_active"
                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                />
                <label for="is_active" class="text-xs font-medium text-gray-700 dark:text-gray-300">Attivo</label>
                <p class="text-xs text-gray-500 dark:text-gray-400">Il prodotto sarà visibile nel catalogo</p>
            </div>

            {{-- Sezione Dinamica: Componenti Prodotto --}}
            <div>
                <div class="flex justify-between items-center mb-2">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Componenti</h4>
                    <button
                    type="button"
                    @click="addComponentRow()"  
                    class="inline-flex items-center px-2 py-1 bg-green-600 rounded text-xs font-semibold text-white hover:bg-green-500 focus:outline-none"
                    >
                    <i class="fas fa-plus mr-1"></i> Aggiungi componente
                    </button>
                </div>

                <template x-for="(item, idx) in form.components" :key="idx">
                    <div class="border rounded-md p-3 mb-3 bg-gray-50 dark:bg-gray-700 relative">
                        {{-- Rimuovi --}}
                        <button
                            type="button"
                            @click="form.components.splice(idx, 1)"
                            class="absolute top-2 right-2 text-red-500 hover:text-red-700"
                        >
                            <i class="fas fa-times-circle"></i>
                        </button>

                        <div class="grid grid-cols-2 gap-2 items-end">
                            {{-- ==================== COMPONENTE (autocomplete) ==================== --}}
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                    Componente
                                </label>

                                {{-- 🔍 input ricerca (visibile finché non c’è item.id) --}}
                                <input type="text"
                                    x-model="item.search"
                                    x-show="!item.id"
                                    x-cloak
                                    @input.debounce.500="searchComponents(idx)"
                                    placeholder="Cerca componente…"
                                    class="mt-1 block w-full px-3 py-2 border rounded-md
                                            bg-white dark:bg-gray-600 text-sm text-gray-900 dark:text-gray-100"
                                >

                                {{-- dropdown risultati --}}
                                <div x-show="item.options.length"
                                    x-cloak
                                    class="absolute z-50 w-full max-w-sm mt-1 bg-white border rounded shadow
                                            max-h-40 overflow-y-auto">
                                    <template x-for="opt in item.options" :key="opt.id">
                                        <div class="px-2 py-1 hover:bg-gray-200 cursor-pointer"
                                            @click="selectComponent(idx,opt)">
                                            <span class="text-xs" x-text="opt.code + ' — '"></span>
                                            <span class="text-xs" x-text="opt.description"></span>
                                        </div>
                                    </template>
                                </div>

                                {{-- riepilogo selezionato --}}
                                <template x-if="item.id">
                                    <div class="mt-1 p-2 border rounded bg-gray-50 dark:bg-gray-700 flex justify-between items-center">
                                        <span class="text-xs">
                                            <strong x-text="item.code"></strong> —
                                            <span x-text="item.description"></span>
                                        </span>

                                        {{-- pulsante Cambia SOLO se !existing --}}
                                        <button type="button"
                                                x-show="!item.existing"
                                                class="text-xs text-red-600 hover:underline ml-2"
                                                @click="removeSelection(idx)">
                                            Cambia
                                        </button>
                                    </div>
                                </template>

                                {{-- hidden field per mantenere il payload identico --}}
                                <input type="hidden"
                                    :name="`components[${idx}][id]`"
                                    :value="item.id">

                                {{-- messaggio errore (★ invariato) --}}
                                <p x-text="errors[`components.${idx}.id`]?.[0]"
                                class="text-red-600 text-xs mt-1"></p>
                            </div>

                            {{-- Quantità --}}
                            <div class="w-28">  {{-- larghezza fissa; regola a piacere --}}
                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                    Quantità
                                    <span
                                        x-text="item.id
                                            ? ' (' + (componentsList.find(c => c.id == item.id)?.unit_of_measure ?? '') + ')'
                                            : ''">
                                    </span>
                                </label>

                                <input  type="number" min="1"
                                        :name="`components[${idx}][quantity]`"
                                        x-model="item.quantity"
                                        class="mt-1 block w-full px-3 py-2 border rounded-md
                                            bg-white dark:bg-gray-600 text-sm text-gray-900 dark:text-gray-100"
                                        required>

                                <p x-text="errors[`components.${idx}.quantity`]?.[0]"
                                class="text-red-600 text-xs mt-1"></p>
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
                @click="showModal = false; resetForm()"
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
