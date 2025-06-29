{{-- resources/views/components/component-supplier-modal.blade.php --}}

@props(['suppliers'])

<div
    x-data="{
        /* ------------------------------------------------------------
         |  stato locale del form (viene popolato dal padre)
         * -----------------------------------------------------------*/
        localForm: {
            component_id : '{{ old('component_id', 'null') }}',
            supplier_id  : '{{ old('supplier_id', '') }}',
            price        : '{{ old('price', '') }}',
            lead_time    : '{{ old('lead_time', '') }}'
        },

        /* Reset campi prezzo + lead-time */
        reset () {
            this.localForm.price     = ''
            this.localForm.lead_time = ''
        },

        /* ------------------------------------------------------------
         |  Carica l’associazione dal server
         * -----------------------------------------------------------*/
        async loadExisting () {
            /* Nessun fornitore selezionato → svuota e basta */
            if (!this.localForm.supplier_id) { this.reset(); return }

            try {
                const qs = new URLSearchParams({
                    component_id: this.localForm.component_id,
                    supplier_id : this.localForm.supplier_id,
                })
                const res = await fetch(`{{ route('price_lists.fetch') }}?${qs}`)
                if (!res.ok) throw new Error('Network')

                const data = await res.json()

                if (data.found) {
                    /* Pivot esistente → riempi gli input */
                    this.localForm.price     = data.price      ?? ''
                    this.localForm.lead_time = data.lead_time ?? ''
                } else {
                    /* Nessun pivot → lascia vuoti */
                    this.reset()
                }
            } catch (e) {
                console.error(e)
                alert('Impossibile recuperare il listino.')
                this.reset()
            }
        }
    }"
    @click.away="showSupplierModal = false"

    {{--  Prefill dal padre + caricamento eventuale associazione  --}}
    @prefill-supplier-form.window="
        localForm = $event.detail;
        $nextTick(() => loadExisting())
    "

    class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-y-auto
           max-h-[90vh] w-full max-w-xl p-6 z-10"
>
    {{-- Header --}}
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Aggiungi a listino fornitore
        </h3>
        <button type="button" @click="showSupplierModal = false" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
        </button>
    </div>

    {{-- Form principale --}}
    <form
        method="POST"
        action="{{ route('price_lists.store') }}"
        @submit=" if(!localForm.supplier_id || !localForm.price){ $event.preventDefault(); alert('Compila tutti i campi'); }"
    >
        @csrf
        <input type="hidden" name="component_id" x-model="localForm.component_id">

        {{-- Fornitore --}}
        <div class="mb-4">
            <label for="supplier_id" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Fornitore</label>
            <select
                x-model="localForm.supplier_id"
                @change="loadExisting()"
                name="supplier_id"
                id="supplier_id"
                required
                class="mt-1 block w-full px-3 py-2 border rounded-md bg-white dark:bg-gray-700 text-sm"
            >
                <option value="">-- Seleziona fornitore --</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Prezzo --}}
        <div class="mb-4">
            <label for="price" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Prezzo (€)</label>
            <input
                x-model="localForm.price"
                name="price"
                id="price"
                type="number" step="0.0001" min="0"
                required
                class="mt-1 block w-full px-3 py-2 border rounded-md bg-white dark:bg-gray-700 text-sm"
            />
        </div>

        {{-- Lead-time --}}
        <div class="mb-6">
            <label for="lead_time" class="block text-xs font-medium text-gray-700 dark:text-gray-300">Tempi di consegna (giorni)</label>
            <input
                x-model="localForm.lead_time"
                name="lead_time"
                id="lead_time"
                type="number" min="0"
                class="mt-1 block w-full px-3 py-2 border rounded-md bg-white dark:bg-gray-700 text-sm"
            />
        </div>

        {{-- Azioni --}}
        <div class="flex justify-end space-x-2">
            <button type="button"
                    @click="reset(); showSupplierModal = false;"
                    class="px-4 py-1.5 text-xs rounded-md bg-gray-200 dark:bg-gray-600 hover:bg-gray-300">
                Annulla
            </button>
            <button type="submit"
                    class="px-4 py-1.5 text-xs rounded-md bg-indigo-600 text-white hover:bg-indigo-500">
                Salva
            </button>
        </div>
    </form>
</div>