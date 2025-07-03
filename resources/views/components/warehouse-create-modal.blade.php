{{-- resources/views/components/warehouse-create-modal.blade.php --}}

{{-- 
  Modale di creazione (solo “create”, niente edit) per i magazzini.
  Campi:
    - code:   codice univoco (es. MG-STOCK)
    - name:   nome descrittivo
    - type:   categoria (stock / import / …) – potrà essere filtrata, ma resta nascosta nella tabella
--}}

<div  @click.away="showModal = false"
      class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-y-auto
             max-h-[90vh] w-full max-w-2xl p-6 z-10">

    {{-- Header --}}
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Nuovo Magazzino</h3>
        <button type="button" @click="showModal = false"
                class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Form --}}
    <form action="{{ route('warehouses.store') }}" method="POST">
        @csrf
        <div class="space-y-4">
            {{-- Codice --}}
            <div>
                <label for="code" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Codice</label>
                <input id="code" name="code" type="text" required
                       class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                              text-sm text-gray-900 dark:text-gray-100">
                @error('code')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Nome --}}
            <div>
                <label for="name" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Nome</label>
                <input id="name" name="name" type="text" required
                       class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                              text-sm text-gray-900 dark:text-gray-100">
                @error('name')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Tipo --}}
            <div>
                <label for="type" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                <select id="type" name="type"
                        class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                    <option value="stock">Stock</option>
                    <option value="commitment">Impegnato</option>
                    <option value="scrap">Scarto</option>
                    {{-- aggiungi altri tipi se necessari --}}
                </select>
                @error('type')
                    <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Footer --}}
        <div class="mt-6 flex justify-end space-x-2">
            <button type="button" @click="showModal = false"
                    class="inline-flex items-center px-3 py-1.5 border rounded-md text-xs font-semibold
                           text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                Annulla
            </button>
            <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 bg-purple-600 rounded-md text-xs font-semibold
                           text-white uppercase hover:bg-purple-500 focus:outline-none focus:ring-2
                           focus:ring-purple-300 transition">
                <i class="fas fa-save mr-1"></i> Salva
            </button>
        </div>
    </form>
</div>
