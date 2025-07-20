{{-- resources/views/components/supplier-component-price-list-modal.blade.php --}}

<div
    x-data="componentPriceListModal()"
    @click.away="
        showComponentPriceListModal = false;
        rows = [];
        supplierId = null;
        supplierName = '';
    "
    x-on:load-component-price-list.window="open($event.detail.supplierId)"
    class="bg-white rounded-lg shadow-lg p-6 w-full max-w-4xl
           max-h-[85vh] overflow-hidden">                      

    <!-- header -->
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold mb-4">
            Componenti a listino – <span x-text="supplierName"></span>
        </h3>
        <button 
            type="button" 
            @click="
                showComponentPriceListModal = false
                rows = [];
                supplierId = null;
                supplierName = '';
            " 
            class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- placeholder mentre carica -->
    <template x-if="loading">
        <p class="text-gray-500 italic">Caricamento…</p>
    </template>

    <template x-if="!loading && rows.length === 0">
        <p class="text-gray-500 italic">Nessun componente abbinato.</p>
    </template>

    <!-- tabella -->
    <div class="max-h-[60vh] overflow-y-auto">
        <table x-show="rows.length" class="table-auto w-full text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-3 py-1">Codice</th>
                    <th class="px-3 py-1">Descrizione</th>
                    <th class="px-3 py-1 text-right">Tempi di consegna&nbsp;(gg)</th>
                    <th class="px-3 py-1 text-right">Prezzo&nbsp;€</th>
                    @can('price_lists.delete')
                        <th class="px-3 py-1 w-12 text-center"></th>
                    @endcan
                </tr>
            </thead>
            <tbody>
                <template x-for="row in rows" :key="row.id">
                    <tr class="border-t">
                        <td class="px-3 py-1" x-text="row.code"></td>
                        <td class="px-3 py-1" x-text="row.description"></td>
                        <td class="px-3 py-1 text-right"
                            x-text="row.pivot.lead_time_days"></td>
                        <td class="px-3 py-1 text-right"
                            x-text="Number(row.pivot.last_cost).toFixed(2)"></td>
                        @can('price_lists.delete')
                            <td class="px-3 py-1 text-center">
                                <button @click="remove(row.id)"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        @endcan
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <!-- footer -->
    <div class="text-right">
        <button
            @click="
                showComponentPriceListModal = false;
                rows = [];
                supplierId = null;
                supplierName = '';
            "
            class="px-4 py-1 bg-gray-200 rounded">Chiudi</button>
    </div>
</div>

<script>
function componentPriceListModal () {
    return {
        supplierId   : null,
        supplierName : '',
        rows         : [],
        loading      : false,

        /* lifecycle: non fa fetch */
        init() {
            this.rows = [];
        },

        /**
         * Chiamato solo dall’evento esterno e con un id valido
         */
        open(id) {
            if (! id) return;          // ulteriore sicurezza

            this.rows       = [];
            this.loading    = true;
            this.supplierId = id;

            this.fetchRows();
        },

        fetchRows () {
            fetch(`/suppliers/${this.supplierId}/price-lists`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(r => {
                if (! r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(json => {
                this.supplierName = json.meta.name;
                this.rows         = json.data;
            })
            .catch(err => alert('Errore: ' + err.message))
            .finally(() => this.loading = false);
        },

        remove (componentId) {
            if (! confirm('Rimuovere il componente dal listino?')) return;

            fetch(`/components/${componentId}/price-lists/${this.supplierId}`, {
                method : 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept'      : 'application/json'
                }
            })
            .then(async res => {
                if (res.ok) {
                    this.rows = this.rows.filter(r => r.id !== componentId);
                } 

                /* --- errore: prova a leggere il JSON ------------------- */
                let msg = 'Errore imprevisto (' + res.status + ')'
                try {
                    const data = await res.json()          // potrebbe fallire
                    if (data?.message) msg = data.message  // messaggio dal backend
                } catch { /* body non JSON: lascia msg di default */ }

                throw new Error(msg)
            })
            .catch(err => alert('Errore: ' + err.message));
        }
    }
}
</script>
