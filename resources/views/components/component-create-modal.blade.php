{{-- resources/views/components/supplier-create-modal.blade.php --}}


@props(['components', 'categories'])


{{-- 
    Componente Create/Edit Fornitore 
    Props:
      - components: collection di Component (usata in modalità 'edit' per popolare il form)
        - categories: collection di Category (usata per il selettore di categoria)
--}}

<div 
    @click.away="showModal = false" 
    class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-y-auto 
            max-h-[90vh] w-full max-w-3xl p-6 z-10"
>
    {{-- Header del modal --}}
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            <span x-text="mode === 'create' ? 'Nuovo Componente' : 'Modifica Componente'"></span>
        </h3>
        <button 
            type="button" 
            @click="showModal = false" 
            class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none"
        >
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Form di creazione/modifica --}}
    <form 
        x-bind:action="mode === 'create' ? '{{ route('components.store') }}' : '{{ url('components') }}/' + form.id" 
        method="POST" 
        @submit.prevent="if(validateComponent()) $el.submit()"
    >
        @csrf

        {{-- Se siamo in edit, aggiungiamo il metodo PUT --}}
        <template x-if="mode === 'edit'">
            <input type="hidden" name="_method" value="PUT" />
        </template>

        <div class="space-y-4">
            {{-- Selettore categoria --}}
            <div>
                <label for="category_id" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Categoria
                </label>
                <select
                    id="category_id"
                    name="category_id"
                    x-model="form.category_id"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                    x-bind:readonly="mode === 'edit'"
                >
                    <option value="">— seleziona —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }} - {{ $cat->description }}</option>
                    @endforeach
                </select>
                <p x-text="errors.category_id?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Codice + Genera --}}
            <div class="flex items-end space-x-2">
                <div class="flex-1">
                    <label for="code" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                        Codice Prodotto
                    </label>
                    <input
                        id="code"
                        name="code"
                        x-model="form.code"
                        type="text"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                        readonly
                    />
                    <p x-text="errors.code?.[0]" class="text-red-600 text-xs mt-1"></p>
                </div>

                {{-- Pulsante genera --}}
                <button
                    type="button"
                    @click="generateCode"
                    class="px-2 py-3 mb-0.5 bg-indigo-600 rounded-md text-xs font-semibold text-white hover:bg-indigo-500"
                    :disabled="!form.category_id"
                    title="Genera codice basato sulla categoria selezionata"
                >
                    <i class="fas fa-magic mr-1"></i> Genera
                </button>
            </div>

            {{-- Descrizione --}}
            <div>
                <label for="description" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Descrizione
                </label>
                <input 
                    id="description" 
                    name="description" 
                    x-model="form.description" 
                    type="text"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />
                <p x-text="errors.description?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Materiale --}}
            <div>
                <label for="material" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Materiale
                </label>
                <input 
                    id="material" 
                    name="material" 
                    x-model="form.material" 
                    type="text"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />
                <p x-text="errors.material?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Lunghezza --}}
            <div>
                <label for="length" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Lunghezza (cm)
                </label>
                <input 
                    id="length" 
                    name="length" 
                    x-model="form.length" 
                    type="text" 
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />
                
                <p x-text="errors.length?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Larghezza --}}
            <div>
                <label for="width" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Larghezza (cm)
                </label>
                <input 
                    id="width" 
                    name="width" 
                    x-model="form.width" 
                    type="text" 
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />                
                <p x-text="errors.width?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Altezza --}}
            <div>
                <label for="height" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Altezza (cm)
                </label>
                <input 
                    id="height" 
                    name="height" 
                    x-model="form.height" 
                    type="url" 
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />
                <p x-text="errors.height?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Peso --}}
            <div>
                <label for="weight" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Peso (kg)
                </label>
                <input
                    id="weight"
                    name="weight"
                    x-model="form.weight"
                    type="text"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                />
                <p x-text="errors.weight?.[0]" class="text-red-600 text-xs mt-1"></p>
            </div>

            {{-- Unità di misura --}}
            <div>
                <label for="unit_of_measure" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Unità di Misura
                </label>
                <input 
                    id="unit_of_measure" 
                    name="unit_of_measure" 
                    x-model="form.unit_of_measure" 
                    type="text"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                />
                <p x-text="errors.unit_of_measure?.[0]" class="text-red-600 text-xs mt-1"></p>
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
                    Componente attivo
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
