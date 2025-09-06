{{-- resources/views/products/partials/product-variables-modal.blade.php --}}

<!--
Modale gestione Variabili & Override per Prodotto
- Whitelist tessuti/colori
- Override maggiorazioni (Δ ≥ 0): percent / per_meter
Tutto via fetch sugli endpoint REST che abbiamo aggiunto.
-->
<div x-data="productVariablesModal()" @open-product-variables.window="open($event.detail.productId)">
    <!-- Overlay -->
    <div x-show="openModal" class="fixed inset-0 bg-black/30 z-40" x-cloak></div>

    <!-- Dialog -->
    <div x-show="openModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" x-cloak>
        <div class="w-full max-w-4xl bg-white dark:bg-gray-800 rounded-xl shadow-lg p-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Variabili prodotto <span class="text-gray-500">#<span x-text="productId"></span></span></h3>
                <button class="text-gray-500 hover:text-gray-700" @click="close()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Tabs -->
            <div class="border-b mb-3">
                <nav class="-mb-px flex space-x-4">
                    <button :class="tab==='whitelist' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500'"
                            class="px-3 py-2 text-sm"
                            @click="tab='whitelist'"><i class="fa-solid fa-list-check mr-1" aria-hidden="true"></i> Whitelist</button>
                    <button :class="tab==='overrides' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500'"
                            class="px-3 py-2 text-sm"
                            @click="tab='overrides'"><i class="fa-solid fa-tags mr-1" aria-hidden="true"></i> Override</button>
                </nav>
            </div>

            <!-- Contenuto Whitelist -->
            <div x-show="tab==='whitelist'">
                <!-- [AGGIUNTA] Avviso errore permessi/opzioni -->
                <template x-if="optionsError">
                    <div class="mb-2 text-xs text-red-600">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                        <span x-text="optionsError"></span>
                    </div>
                </template>

                <!-- [AGGIUNTA] Stato vuoto quando non ci sono opzioni attive -->
                <template x-if="!optionsError && options.fabrics.length === 0 && options.colors.length === 0">
                    <div class="mb-2 text-xs text-amber-600">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Nessun tessuto/colore attivo trovato. Verifica l’anagrafica <b>Tessuti</b> e <b>Colori</b>.
                    </div>
                </template>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h4 class="text-sm font-medium mb-2">Tessuti attivi</h4>
                        <div class="flex items-center justify-between mb-2 gap-2">
                            <div class="relative flex-1">
                                <i class="fa-solid fa-magnifying-glass absolute left-2 top-2.5 text-gray-400 text-xs"></i>
                                <input type="text"
                                    class="border rounded px-6 py-1.5 w-full text-sm"
                                    placeholder="Cerca tessuti…"
                                    x-model="filterFabric">
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" class="px-2 py-1 text-xs rounded border"
                                        @click="selectAllFabrics(true)">
                                    <i class="fa-solid fa-check-double mr-1"></i> Seleziona tutto
                                </button>
                                <button type="button" class="px-2 py-1 text-xs rounded border"
                                        @click="selectAllFabrics(false)">
                                    <i class="fa-solid fa-xmark mr-1"></i> Deseleziona
                                </button>
                            </div>
                        </div>
                        <p class="text-[11px] text-gray-500 mb-1">
                            Selezionati: <span x-text="whitelist.fabric_ids.length"></span>
                        </p>
                        <div class="h-56 overflow-y-auto border rounded p-2">
                            <template x-for="f in filteredFabrics()" :key="`fab-${f.id}`">
                                <label class="flex items-center gap-2 text-sm py-1">
                                    <input type="checkbox" :value="f.id" x-model="whitelist.fabric_ids">
                                    <span x-text="f.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium mb-2">Colori attivi</h4>
                        <div class="flex items-center justify-between mb-2 gap-2">
                            <div class="relative flex-1">
                                <i class="fa-solid fa-magnifying-glass absolute left-2 top-2.5 text-gray-400 text-xs"></i>
                                <input type="text"
                                    class="border rounded px-6 py-1.5 w-full text-sm"
                                    placeholder="Cerca colori…"
                                    x-model="filterColor">
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" class="px-2 py-1 text-xs rounded border"
                                        @click="selectAllColors(true)">
                                <i class="fa-solid fa-check-double mr-1"></i> Seleziona tutto
                                </button>
                                <button type="button" class="px-2 py-1 text-xs rounded border"
                                        @click="selectAllColors(false)">
                                <i class="fa-solid fa-xmark mr-1"></i> Deseleziona
                                </button>
                            </div>
                        </div>
                        <p class="text-[11px] text-gray-500 mb-1">
                            Selezionati: <span x-text="whitelist.color_ids.length"></span>
                        </p>
                        <div class="h-56 overflow-y-auto border rounded p-2">
                            <template x-for="c in filteredColors()" :key="`col-${c.id}`">
                                <label class="flex items-center gap-2 text-sm py-1">
                                    <input type="checkbox" :value="c.id" x-model="whitelist.color_ids">
                                    <span x-text="c.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button class="px-3 py-1.5 text-sm rounded border" @click="close()">Chiudi</button>
                    <button class="px-3 py-1.5 text-sm rounded bg-indigo-600 text-white"
                            @click="saveWhitelist()">Salva whitelist</button>
                </div>
            </div>

            <!-- Contenuto Override -->
            <div x-show="tab==='overrides'">
                <p class="text-xs text-gray-500 mb-2">
                    Le maggiorazioni (Δ) sono sempre ≥ 0. Tipi: <b>percent</b> (su prezzo base unitario), <b>per_meter</b> (€/m × metri BOM).
                </p>

                <!-- Override per Tessuto -->
                <div class="mb-4">
                    <h4 class="text-sm font-medium mb-2">Override per Tessuto</h4>
                            
                    <!-- Stato vuoto -->
                    <template x-if="whitelistedFabrics().length === 0">
                        <p class="text-xs text-amber-600 mb-2">
                            Nessun tessuto in whitelist. Vai alla tab <b>Whitelist</b>, seleziona e salva.
                        </p>
                    </template>

                    <template x-if="whitelistedFabrics().length > 0">
                        <div class="border rounded">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-1 text-left">Tessuto</th>
                                        <th class="px-2 py-1 text-left">Tipo</th>
                                        <th class="px-2 py-1 text-left">Valore</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="f in whitelistedFabrics()" :key="`ovf-${f.id}`">
                                    <tr class="border-t">
                                        <td class="px-2 py-1" x-text="f.name"></td>
                                        <td class="px-2 py-1">
                                            <select class="border rounded px-2 py-1"
                                                    x-model="overrides.fabrics[f.id].surcharge_type">
                                                <option value="">—</option>
                                                <option value="percent">% su base</option>
                                                <option value="per_meter">€/m</option>
                                            </select>
                                        </td>
                                        <td class="px-2 py-1">
                                            <input type="number" step="0.01" min="0"
                                                    class="border rounded px-2 py-1 w-28"
                                                    x-model.number="overrides.fabrics[f.id].surcharge_value">
                                        </td>
                                    </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                <!-- Override per Colore -->
                <div class="mb-4">
                    <h4 class="text-sm font-medium mb-2">Override per Colore</h4>
                    <template x-if="whitelistedColors().length === 0">
                        <p class="text-xs text-amber-600 mb-2">
                            Nessun colore in whitelist. Vai alla tab <b>Whitelist</b>, seleziona e salva.
                        </p>
                    </template>

                    <template x-if="whitelistedColors().length > 0">
                        <div class="border rounded">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                    <th class="px-2 py-1 text-left">Colore</th>
                                    <th class="px-2 py-1 text-left">Tipo</th>
                                    <th class="px-2 py-1 text-left">Valore</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="c in whitelistedColors()" :key="`ovc-${c.id}`">
                                    <tr class="border-t">
                                        <td class="px-2 py-1" x-text="c.name"></td>
                                        <td class="px-2 py-1">
                                        <select class="border rounded px-2 py-1"
                                                x-model="overrides.colors[c.id].surcharge_type">
                                            <option value="">—</option>
                                            <option value="percent">% su base</option>
                                            <option value="per_meter">€/m</option>
                                        </select>
                                        </td>
                                        <td class="px-2 py-1">
                                        <input type="number" step="0.01" min="0"
                                                class="border rounded px-2 py-1 w-28"
                                                x-model.number="overrides.colors[c.id].surcharge_value">
                                        </td>
                                    </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                <!-- Override per Coppia -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-sm font-medium">Override per Coppia (Tessuto×Colore)</h4>
                        
                        <template x-if="whitelistedFabrics().length > 0 && whitelistedColors().length > 0">
                            <button class="px-2 py-1 text-xs rounded border"
                                    @click="addPairRow()">
                                <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i> Aggiungi coppia
                            </button>
                        </template>
                    </div>
                    <template x-if="whitelistedFabrics().length === 0 || whitelistedColors().length === 0">
                        <p class="text-xs text-amber-600 mb-2">
                            Per aggiungere coppie, seleziona e salva prima almeno un <b>tessuto</b> e un <b>colore</b> nella tab Whitelist.
                        </p>
                    </template>
                    <template x-if="whitelistedFabrics().length > 0 && whitelistedColors().length > 0">
                        <div class="border rounded">
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-1 text-left">Tessuto</th>
                                        <th class="px-2 py-1 text-left">Colore</th>
                                        <th class="px-2 py-1 text-left">Tipo</th>
                                        <th class="px-2 py-1 text-left">Valore</th>
                                        <th class="px-2 py-1"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(row, idx) in overrides.pairs" :key="`pair-${idx}-${row.fabric_id}-${row.color_id}`">
                                        <tr class="border-t">
                                            <td class="px-2 py-1">
                                                <select class="border rounded px-2 py-1"
                                                        x-model.number="row.fabric_id">
                                                    <option value="">—</option>
                                                    <template x-for="f in whitelistedFabrics()" :key="`pf-${f.id}`">
                                                        <option :value="f.id" x-text="f.name"></option>
                                                    </template>
                                                </select>
                                            </td>
                                            <td class="px-2 py-1">
                                                <select class="border rounded px-2 py-1"
                                                        x-model.number="row.color_id">
                                                    <option value="">—</option>
                                                    <template x-for="c in whitelistedColors()" :key="`pc-${c.id}`">
                                                        <option :value="c.id" x-text="c.name"></option>
                                                    </template>
                                                </select>
                                            </td>
                                            <td class="px-2 py-1">
                                                <select class="border rounded px-2 py-1"
                                                        x-model="row.surcharge_type">
                                                    <option value="">—</option>
                                                    <option value="percent">% su base</option>
                                                    <option value="per_meter">€/m</option>
                                                </select>
                                            </td>
                                            <td class="px-2 py-1">
                                                <input type="number" step="0.01" min="0"
                                                        class="border rounded px-2 py-1 w-28"
                                                        x-model.number="row.surcharge_value">
                                            </td>
                                            <td class="px-2 py-1">
                                                <button class="text-red-600" @click="removePairRow(idx)">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                    <template x-if="overrides.pairs.length === 0">
                                        <tr>
                                            <td colspan="5" class="text-center text-gray-500">
                                                Nessun override applicato
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <button class="px-3 py-1.5 text-sm rounded border" @click="close()">Chiudi</button>
                    <button class="px-3 py-1.5 text-sm rounded bg-indigo-600 text-white"
                            @click="saveOverrides()">Salva override</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /**
     * Alpine component per la gestione del modale Variabili.
     * - Carica opzioni globali (tessuti/colori attivi)
     * - Carica whitelist e override del prodotto
     * - Salva whitelist e override su endpoint dedicati
     */
    function productVariablesModal() {
        return {
            openModal: false,
            productId: null,
            tab: 'whitelist',
            optionsError: '',
            options: { fabrics: [], colors: [] },
            whitelist: { fabric_ids: [], color_ids: [] },
            overrides: {
                fabrics: {},  // es: { [fabric_id]: { surcharge_type, surcharge_value } }
                colors:  {},  // es: { [color_id]:  { surcharge_type, surcharge_value } }
                pairs:   []   // es: [ { fabric_id, color_id, surcharge_type, surcharge_value } ]
            },
            filterFabric: '',
            filterColor:  '',

            async open(productId) {
                this.openModal = true;
                this.productId = productId;
                this.tab = 'whitelist';
                await this.loadOptions();
                await this.loadWhitelist();
                await this.loadOverrides();
            },
            close() {
                this.openModal = false;
                this.productId = null;
                this.whitelist = { fabric_ids: [], color_ids: [] };
                this.overrides = { fabrics: {}, colors: {}, pairs: [] };
            },

            // --- Helpers per ottenere solo whitelists nominative (per tabelle override) ---
            whitelistedFabrics() {
                const ids = new Set(this.whitelist.fabric_ids.map(Number));
                return this.options.fabrics.filter(f => ids.has(Number(f.id)));
            },
            whitelistedColors() {
                const ids = new Set(this.whitelist.color_ids.map(Number));
                return this.options.colors.filter(c => ids.has(Number(c.id)));
            },

            // ---- Loaders ---------------------------------------------------------------
            async loadOptions() {
                this.optionsError = '';
                const res = await fetch('{{ route('products.variables.options') }}', { headers: { 'Accept':'application/json' } });
                if (!res.ok) {
                    // 403 tipico: permesso non presente (mismatch)
                    const txt = await res.text().catch(()=> '');
                    this.options = { fabrics: [], colors: [] };
                    this.optionsError = (res.status === 403)
                        ? 'Permesso mancante per leggere le opzioni (products.update o product-variables.update).'
                        : 'Errore nel caricamento delle opzioni.';
                    console.error('getVariableOptions failed', res.status, txt);
                    return;
                }
                this.options = await res.json();
            },
            async loadWhitelist() {
                const url = '{{ url('products') }}/' + this.productId + '/variables';
                const res = await fetch(url, { headers: { 'Accept':'application/json' } });
                if (res.ok) {
                    const js = await res.json();
                    this.whitelist.fabric_ids = js.fabric_ids ?? [];
                    this.whitelist.color_ids  = js.color_ids ?? [];
                }
            },
            async loadOverrides() {
                const url = '{{ url('products') }}/' + this.productId + '/variable-overrides';
                const res = await fetch(url, { headers: { 'Accept':'application/json' } });
                if (res.ok) {
                    const js = await res.json();
                    // Normalizza fabrics/colors in mappe indicizzate per ID
                    this.overrides.fabrics = {};
                    (js.fabrics || []).forEach(r => {
                        this.overrides.fabrics[r.fabric_id] = {
                            surcharge_type: r.surcharge_type ?? '',
                            surcharge_value: r.surcharge_value ?? 0
                        };
                    });
                    this.overrides.colors = {};
                    (js.colors || []).forEach(r => {
                        this.overrides.colors[r.color_id] = {
                            surcharge_type: r.surcharge_type ?? '',
                            surcharge_value: r.surcharge_value ?? 0
                        };
                    });
                    this.overrides.pairs = (js.pairs || []).map(r => ({
                        fabric_id: r.fabric_id, color_id: r.color_id,
                        surcharge_type: r.surcharge_type ?? '',
                        surcharge_value: r.surcharge_value ?? 0
                    }));
                }
            },

            // ---- Azioni Salvataggio ----------------------------------------------------
            async saveWhitelist() {
                const url = '{{ url('products') }}/' + this.productId + '/variables';
                const payload = { fabrics: this.whitelist.fabric_ids, colors: this.whitelist.color_ids };
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type':'application/json',
                        'Accept':'application/json',
                        'X-CSRF-TOKEN':'{{ csrf_token() }}',
                        'X-Requested-With':'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });
                if (res.ok) {
                    // ricarichiamo whitelist *prima* di passare agli override,
                    // così le select per coppie si popolano correttamente
                    await this.loadWhitelist();
                    await this.loadOverrides();
                    this.tab = 'overrides';
                } else {
                    alert('Errore nel salvataggio della whitelist.');
                }
            },

            async saveOverrides() {
                // Preparo payload normalizzando: prendo solo righe con tipo valorizzato
                const fabrics = Object.entries(this.overrides.fabrics)
                    .filter(([id, v]) => v.surcharge_type && v.surcharge_value >= 0)
                    .map(([fabric_id, v]) => ({ fabric_id: Number(fabric_id), ...v }));

                const colors = Object.entries(this.overrides.colors)
                    .filter(([id, v]) => v.surcharge_type && v.surcharge_value >= 0)
                    .map(([color_id, v]) => ({ color_id: Number(color_id), ...v }));

                const pairs = (this.overrides.pairs || [])
                    .filter(r => r.fabric_id && r.color_id && r.surcharge_type && r.surcharge_value >= 0)
                    .map(r => ({
                        fabric_id: Number(r.fabric_id),
                        color_id:  Number(r.color_id),
                        surcharge_type: r.surcharge_type,
                        surcharge_value: Number(r.surcharge_value)
                    }));

                const url = '{{ url('products') }}/' + this.productId + '/variable-overrides';
                const payload = { fabrics, colors, pairs };

                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}' },
                    body: JSON.stringify(payload)
                });
                if (res.ok) {
                    alert('Override salvati.');
                    this.close();
                } else {
                    alert('Errore nel salvataggio degli override.');
                }
            },

            // ---- Gestione righe coppia -------------------------------------------------
            addPairRow() {
                const wf = this.whitelistedFabrics();
                const wc = this.whitelistedColors();
                this.overrides.pairs.push({
                    fabric_id: wf[0]?.id ?? '',
                    color_id:  wc[0]?.id ?? '',
                    surcharge_type: '',
                    surcharge_value: 0
                });
            },
            removePairRow(idx) {
                this.overrides.pairs.splice(idx, 1);
            },            

            /** Ritorna i tessuti filtrati per il testo di ricerca */
            filteredFabrics() {
                if (!this.filterFabric) return this.options.fabrics;
                const q = this.filterFabric.toLowerCase();
                return this.options.fabrics.filter(f => String(f.name).toLowerCase().includes(q));
            },

            /** Ritorna i colori filtrati per il testo di ricerca */
            filteredColors() {
                if (!this.filterColor) return this.options.colors;
                const q = this.filterColor.toLowerCase();
                return this.options.colors.filter(c => String(c.name).toLowerCase().includes(q));
            },

            /**
             * Seleziona/Deseleziona TUTTI i tessuti della lista filtrata
             * @param {boolean} select  true = seleziona; false = deseleziona
             */
            selectAllFabrics(select = true) {
                const idsFiltered = new Set(this.filteredFabrics().map(f => Number(f.id)));
                if (select) {
                    // unione (evita duplicati)
                    this.whitelist.fabric_ids = Array.from(
                        new Set([...this.whitelist.fabric_ids.map(Number), ...idsFiltered])
                    );
                } else {
                    // rimuove solo quelli visibili col filtro
                    this.whitelist.fabric_ids = this.whitelist.fabric_ids
                    .map(Number)
                    .filter(id => !idsFiltered.has(id));
                }
            },

            /**
             * Seleziona/Deseleziona TUTTI i colori della lista filtrata
             */
            selectAllColors(select = true) {
                const idsFiltered = new Set(this.filteredColors().map(c => Number(c.id)));
                if (select) {
                    this.whitelist.color_ids = Array.from(
                        new Set([...this.whitelist.color_ids.map(Number), ...idsFiltered])
                    );
                } else {
                    this.whitelist.color_ids = this.whitelist.color_ids
                    .map(Number)
                    .filter(id => !idsFiltered.has(id));
                }
            },
        }
    }
</script>
