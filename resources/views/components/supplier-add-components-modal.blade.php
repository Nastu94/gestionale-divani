{{-- resources/views/components/supplier-add-components-modal.blade.php --}}

@props(['components'])


<div
    x-data="addComponentsModal()"
    x-on:load-component-items.window="open($event.detail.supplier_id)"
    x-show="showAddComponentModal"
    x-cloak
    class="relative bg-white rounded-lg shadow-lg overflow-y-auto max-h-[90vh] w-full max-w-2xl p-6 z-10"
>

    {{-- Header --}}
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">
            Componenti a listino – <span x-text="supplierName"></span>
        </h3>
        <button type="button" @click="close()" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <template x-if="loading">
        <p class="text-gray-500 italic py-8 text-center">Caricamento…</p>
    </template>

    {{-- Sezioni dinamiche -------------------------------------------------- --}}
    <div x-show="!loading">
        <div class="space-y-4">
            <template x-for="(item, idx) in items" :key="idx">
                <div class="border rounded-md p-3 bg-gray-50 relative">
                    {{-- rimuovi sezione --}}
                    <button type="button"
                            @click="items.splice(idx,1)"
                            class="absolute z-10 top-2 right-2 text-red-600 hover:text-red-800">
                        <i class="fas fa-times-circle"></i>
                    </button>

                    {{-- Componente --}}
                    <div class="relative mb-3" x-data>
                        <label class="block text-xs font-medium">Cerca componente</label>

                        <!-- input ricerca -->
                        <input
                            x-show="!item.component_id" 
                            x-cloak
                            type="text"
                            x-model="item.search"
                            @input.debounce.500="rowSearch(idx)"
                            placeholder="Codice o descrizione…"
                            class="w-full border rounded p-1 mt-1 text-sm"
                        >
                        <p x-show="!item.component_id" x-cloak class="text-[10px] text-gray-500 mt-0.5">
                            Usa % per jolly (es. <code>CUS%01</code>)
                        </p>

                        <!-- lista suggerimenti -->
                        <div
                            x-show="item.options.length"
                            x-cloak
                            class="absolute z-50 w-full bg-white border rounded shadow max-h-40 overflow-y-auto mt-1"
                        >
                            <template x-for="opt in item.options" :key="opt.id">
                                <div
                                    class="px-2 py-1 hover:bg-gray-200 cursor-pointer text-xs"
                                    @click="selectRowComponent(idx, opt)"
                                >
                                    <strong x-text="opt.code"></strong> — <span x-text="opt.description"></span>
                                </div>
                            </template>
                        </div>

                        <!-- riepilogo scelto -->
                        <template x-if="item.component_id">
                            <div class="mt-2 p-2 border rounded bg-white/60 text-xs">
                                <p>
                                    <strong x-text="item.code"></strong> — <span x-text="item.description"></span>
                                </p>
                                <p>
                                    UM: <span class="uppercase" x-text="item.unit_of_measure"></span>
                                </p>
                                <button type="button" @click="clearRow(idx)"
                                        x-show="!item.code"
                                        class="text-red-600 hover:text-red-800 mt-1">Cambia</button>
                            </div>
                        </template>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        {{-- Prezzo --}}
                        <div>
                            <label class="block text-xs font-medium">Prezzo €</label>
                            <input type="number" step="0.0001" min="0"
                                x-model="item.price"
                                class="mt-1 block w-full border rounded p-1 text-right">
                        </div>
                        {{-- Tempi di consegna --}}
                        <div>
                            <label class="block text-xs font-medium">Tempi di consegna (giorni)</label>
                            <input type="number" step="1" min="0"
                                x-model="item.lead_time"
                                class="mt-1 block w-full border rounded p-1 text-right">
                        </div>
                    </div>
                </div>
            </template>

            {{-- Aggiungi sezione --}}
            <button type="button"
                    @click="items.push(emptyItem())"
                    class="inline-flex items-center px-2 py-1 bg-green-600 rounded text-xs font-semibold text-white hover:bg-green-500">
                <i class="fas fa-plus mr-1"></i> Aggiungi componente
            </button>
        </div>
    </div>

    {{-- Footer ------------------------------------------------------------- --}}
    <div class="mt-6 flex justify-end space-x-2">
        <button type="button" @click="close()" class="px-4 py-1 bg-gray-200 rounded">Annulla</button>
        <button type="button" @click="submit(); close()"
                class="px-4 py-1 bg-emerald-600 text-white rounded">Salva</button>
    </div>
</div>

<script>
function addComponentsModal () {
    return {
        supplierId : null,
        supplierName : '',   
        items      : [],
        loading      : false,

        /* --- APERTURA MODALE E CARICAMENTO DATI ----------------------------- */
        open (id) {
            this.supplierId = id
            this.items      = []
            this.supplierName = '' 
            this.loading    = true

            /* GET già esistente: suppliers/{id}/price-lists */
            fetch(`/suppliers/${id}/price-lists`, {
                headers : { 'Accept':'application/json' }
            })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(json => {
                this.supplierName = json.meta.name  
                /* mappa i componenti esistenti */
                this.items = json.data.map(c => ({
                    /* dati identificativi */
                    component_id    : c.id,
                    code            : c.code,
                    description     : c.description,
                    unit_of_measure : c.unit_of_measure,
                    last_cost       : c.pivot.last_cost,

                    /* campi editabili */
                    price           : c.pivot.last_cost,
                    lead_time       : c.pivot.lead_time_days,

                    /* campi interni alla ricerca */
                    search          : '',
                    options         : [],
                }))

                /* se non ce n’è nemmeno uno → aggiungi sezione vuota */
                if (! this.items.length) this.items.push(this.emptyItem())
            })
            .catch(() => {
                /* se la fetch fallisce, mostra sezione vuota */
                this.items = [ this.emptyItem() ]
            })
            .finally(() => this.loading = false)
        },

        /* riga vuota estesa con campi utili alla ricerca */
        emptyItem () {
            return {
                component_id   : '',
                code           : '',
                description    : '',
                unit_of_measure: '',
                last_cost      : '',
                price          : '',
                lead_time      : '',
                search         : '',
                options        : [],
            }
        },

        /* --- CHIUSURA MODALE --------------------------------------------- */
        close () {
            this.showAddComponentModal = false
            this.items = []
            this.supplierId = null
        },
        
        /* --- AUTOCOMPLETE -------------------------------------------- */
        async rowSearch (idx) {
            const row  = this.items[idx]
            const term = row.search.trim()
            if (term.length < 2) { row.options = []; return }

            const url = `/components/search?q=${encodeURIComponent(term)}`

            try {
                const res = await fetch(url, { headers:{Accept:'application/json'} })
                if (!res.ok) throw new Error(res.status)
                row.options = await res.json()
            } catch { row.options = [] }
        },

        /* --- SELEZIONE RIGA COMPONENTE ---------------------------------- */
        selectRowComponent (idx, opt) {
            this.items[idx] = {
                ...this.items[idx],
                component_id    : opt.id,
                code            : opt.code,
                description     : opt.description,
                unit_of_measure : opt.unit_of_measure,
                last_cost       : opt.last_cost ?? '',
                search          : '',
                options         : [],
                /* opzionale: pre-compila prezzo con last_cost */
                price           : opt.last_cost ?? this.items[idx].price,
            }
        },

        /* --- PULIZIA RIGA ----------------------------------------------- */
        clearRow (idx) {
            this.items[idx] = { ...this.emptyItem(), price:'', lead_time:'' }
        },

        /* --- INVIO DATI -------------------------------------------------- */
        submit () {
            if (this.items.some(i => ! i.component_id || ! i.price)) {
                alert('Compila tutti i campi obbligatori')
                return
            }
            fetch(`/suppliers/${this.supplierId}/price-lists`, {
                method  : 'POST',
                headers : {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept'      : 'application/json',
                    'Content-Type': 'application/json'
                },
                body : JSON.stringify({ items : this.items })
            })
            .then(r => r.ok ? alert('Componenti aggiunti con successo') : r.json().then(j => Promise.reject(j)))
            .catch(err => alert('Errore: ' + (err.message ?? 'dati non validi')))
        }
    }
}
</script>
