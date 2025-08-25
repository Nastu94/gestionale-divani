{{-- resource/views/components/component_categories-create-modal.blade.php --}}

@props(['categories'])

@php
    use App\Enums\ProductionPhase;
    // escluse fasi 0 e 6
    $prodPhases = collect([
        ProductionPhase::STRUCTURE,
        ProductionPhase::PADDING,
        ProductionPhase::UPHOLSTERY,
        ProductionPhase::ASSEMBLY,
        ProductionPhase::FINISHING,
    ]);
@endphp

<div 
    class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-y-auto 
            max-h-[90vh] w-full max-w-3xl p-6 z-10"
>
    {{-- Header del modal --}}
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            <span x-text="mode === 'create' ? 'Nuova Categoria' : 'Modifica Categoria'"></span>
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
        x-bind:action="mode === 'create' ? '{{ route('categories.store') }}' : '{{ url('categories') }}/' + form.id" 
        method="POST" 
        @submit.prevent="if(validateCategory()) $el.submit()"
    >
        @csrf

        {{-- Se siamo in edit, aggiungiamo il metodo PUT --}}
        <template x-if="mode === 'edit'">
            <input type="hidden" name="_method" value="PUT" />
        </template>

        {{-- Input nascosti per ID e modalità --}}
        <input type="hidden" name="id" x-model="form.id" />

        <div class="space-y-4">
            {{-- Codice + Genera --}}
            <div class="flex items-end space-x-2">
                <div class="flex-1">
                    <label for="code" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                        Codice Categoria
                    </label>
                    <input
                        id="code"
                        name="code"
                        x-model="form.code"
                        type="text"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100"
                    />
                    <p x-text="errors.code?.[0]" class="text-red-600 text-xs mt-1"></p>
                </div>
            </div>

            {{-- Nome Categoria --}}
            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                    Nome Categoria
                </label>
                <input 
                    id="name" 
                    name="name" 
                    x-model="form.name" 
                    type="text"
                    class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700 text-sm text-gray-900 dark:text-gray-100" 
                />
                <p x-text="errors.name?.[0]" class="text-red-600 text-xs mt-1"></p>
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

            {{-- Fasi di produzione --}}
            <div>
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Fasi di produzione
                </label>

                <fieldset class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    @foreach ($prodPhases as $ph)
                        <label class="inline-flex items-center space-x-2 text-sm">
                            <input type="checkbox"
                                name="phases[]"
                                :value="'{{ $ph->value }}'"
                                x-model="form.phases"
                                class="rounded border-gray-300 focus:ring-purple-500
                                        text-purple-600 dark:bg-gray-700 dark:border-gray-600">
                            <span>{{ $ph->label() }} ({{ $ph->value }})</span>
                        </label>
                    @endforeach
                </fieldset>

                <p x-text="errors.phases" class="text-red-600 text-xs mt-1"></p>
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
