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
        },
        supplierSearch  : '',
        supplierOptions : [],

        async searchSuppliers() {
            if (this.supplierSearch.trim().length < 2) { this.supplierOptions=[]; return; }
            try {
                const r = await fetch(`/suppliers/search?q=${encodeURIComponent(this.supplierSearch.trim())}`,
                                    { headers:{Accept:'application/json'} })
                if (!r.ok) throw new Error(r.status)
                this.supplierOptions = await r.json()
            } catch { this.supplierOptions = [] }
        },
        selectSupplier(opt) {
            this.localForm.supplier_id = opt.id
            this.localForm.name        = opt.name
            this.localForm.email       = opt.email
            this.supplierSearch        = ''
            this.supplierOptions       = []
            this.loadExisting()            // carica price/lead-time già presenti
        },
        clearSupplier() {
            this.localForm.supplier_id = ''
            this.localForm.name        = ''
            this.localForm.email       = ''
            this.reset()
        },
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

        {{-- FORNITORE ------------------------------------------------------- --}}
        <div class="mb-4" x-data>
            <label class="block text-xs font-medium">Cerca fornitore</label>

            <!-- input ricerca -->
            <input
                x-show="!localForm.supplier_id"
                x-cloak
                x-model="supplierSearch"
                @input.debounce.500="searchSuppliers"
                type="text"
                placeholder="Nome, P.IVA o C.F…"
                class="w-full mt-1 border rounded p-1 text-sm"
            >
            <p x-show="!localForm.supplier_id" x-cloak class="text-[10px] text-gray-500 mt-0.5">
                Usa % per jolly (es. <code>CUS%01</code>)
            </p>
            <!-- suggerimenti -->
            <div x-show="supplierOptions.length" x-cloak
                class="absolute z-50 w-full bg-white border rounded shadow max-h-40 overflow-y-auto mt-1">
                <template x-for="opt in supplierOptions" :key="opt.id">
                    <div class="px-2 py-1 hover:bg-gray-200 cursor-pointer text-xs"
                        @click="selectSupplier(opt)">
                        <strong x-text="opt.name"></strong>
                        <template x-if="opt.vat_number">
                            <span x-text="' – ' + opt.vat_number"></span>
                        </template>
                    </div>
                </template>
            </div>

            <!-- riepilogo scelto -->
            <template x-if="localForm.supplier_id">
                <div class="mt-2 p-2 border rounded bg-gray-50 text-xs">
                    <p><strong x-text="localForm.name"></strong></p>
                    <p x-text="localForm.email"></p>
                    <button type="button" @click="clearSupplier"
                            class="text-red-600 mt-1">Cambia</button>
                </div>
            </template>

            <!-- hidden id per il POST -->
            <input type="hidden" name="supplier_id" x-model="localForm.supplier_id">
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