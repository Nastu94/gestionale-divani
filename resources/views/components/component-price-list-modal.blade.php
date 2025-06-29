<!-- component-price-list-modal.blade.php -->
<div
    x-data="priceListModal()"
    @click.away="
        showPriceListModal = false;
        rows = [];
        componentId = null;
        componentCode = '';
        componentDescr = '';
    "
    x-on:load-price-list.window="init($event.detail.component_id)"
    class="bg-white rounded-lg shadow-lg p-6 w-full">

    <!-- header -->
    <h2 class="text-xl font-semibold mb-4">
        Listino fornitori –
        <span x-text="componentCode"></span>
        <span class="text-gray-500" x-show="componentDescr">
            – <span x-text="componentDescr"></span>
        </span>
    </h2>

    <!-- tabella -->
    <template x-if="loading">
        <p class="text-gray-500 italic">Caricamento...</p>
    </template>

    <template x-if="!loading && rows.length === 0">
        <p class="text-gray-500 italic">Nessun fornitore abbinato.</p>
    </template>

    <table x-show="rows.length" class="table-auto w-full text-sm mb-4">
        <thead>
            <tr class="bg-gray-100">
                <th class="px-3 py-1 text-left">Fornitore</th>
                <th class="px-3 py-1 text-right">Prezzo&nbsp;(€)</th>
                <th class="px-3 py-1 text-right">Lead-time&nbsp;(gg)</th>
                <th class="px-3 py-1 w-12"></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="row in rows" :key="row.id">
                <tr class="border-t">
                    <td class="px-3 py-1"   x-text="row.name"></td>
                    <td class="px-3 py-1 text-right"
                        x-text="Number(row.pivot.last_cost).toFixed(2)"></td>
                    <td class="px-3 py-1 text-right"
                        x-text="row.pivot.lead_time_days"></td>
                    <td class="px-3 py-1 text-center">
                        <button
                            @click="remove(row.id)"
                            class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>

    <!-- footer -->
    <div class="text-right">
        <button
            @click="
                showPriceListModal = false;
                /* ② reset anche in chiusura manuale */
                rows = [];
                componentId = null;
                componentCode = '';
                componentDescr = '';
            "
            class="px-4 py-1 bg-gray-200 rounded"
        >
            Chiudi
        </button>
    </div>
</div>

<script>
/**
 * Componente Alpine che popola il modale listino-prezzi
 * =====================================================
 */
function priceListModal () {
    return {
        componentId : null,
        componentCode  : '',
        componentDescr : '',
        rows        : [],
        loading     : false,

        /** Listener chiamato dall'evento globale */
        init (id) {
            if (id === undefined) return   // ignora il render iniziale
            this.componentId = id
            this.fetchRows()
        },

        /** Recupera i fornitori */
        fetchRows () {
            if (! this.componentId) {
                console.warn('[Modal] fetchRows() – componentId mancante')
                return
            }

            this.loading = true
            fetch(`/components/${this.componentId}/price-lists`, {
                headers : { 'Accept' : 'application/json' }
            })
            .then(res => {
                return res.json()
            })
            .then(json => {
                // meta-dati per l’intestazione del modale
                this.componentCode  = json.meta.code
                this.componentDescr = json.meta.description

                // righe tabella
                this.rows = json.data
            })
            .catch(err => console.error('[Modal] fetchRows() – errore', err))
            .finally(() => this.loading = false)
        },

        /** Cancella una riga dal listino */
        remove (supplierId) {
            if (! confirm('Rimuovere il componente dal listino di questo fornitore?')) return

            fetch(`/components/${this.componentId}/price-lists/${supplierId}`, {
                method  : 'DELETE',
                headers : {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept'      : 'application/json'
                }
            })
            .then(res => {
                if (res.ok) {
                    this.rows = this.rows.filter(r => r.id !== supplierId)  // ← filtra su id
                } else {
                    throw new Error('HTTP ' + res.status)
                }
            })
            .catch(err => alert('Errore: ' + err.message))
        }
    }
}
</script>
