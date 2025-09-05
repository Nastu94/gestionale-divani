{{-- resources/views/components/product-variable-matching-modal.blade.php --}}
{{-- ----------------------------------------------------------------------------
Modale "Abbina tessuto × colore" ad un componente TESSU (solo front-end).
- Apertura: evento Alpine `$dispatch('open-matching-modal', {...})`
  payload richiesto:
    componentId:int, code:string, description:string,
    fabricId:int|null, fabricName?:string, colorId:int|null, colorName?:string
- Props (dal parent):
    $fabrics : Collection<App\Models\Fabric> attivi
    $colors  : Collection<App\Models\Color>  attivi
    $matrix  : array [fabric_id][color_id] => ['id'=>component_id,'code'=>..., 'is_active'=>bool]
- Nessuna chiamata back-end: il tasto "Salva" è disabilitato con tooltip.
  Quando attiveremo i metodi controller, basterà agganciare il submit via fetch()
  verso l'endpoint protetto da "product-variables.manage".
---------------------------------------------------------------------------- --}}

@php
    /** @var \Illuminate\Support\Collection $fabrics */
    /** @var \Illuminate\Support\Collection $colors */
    /** @var array $matrix */
    /** @var array $fabricAliases */
    /** @var array $colorAliases */
    /** @var array $ambiguousColorTerms */
@endphp

<div
    x-data="variableMatchingModal()"
    x-init="
        // Listener globale: apre il modale con i dati della riga
        window.addEventListener('open-matching-modal', (e) => open(e.detail));
    "
    x-show="openModal"
    x-cloak
    class="fixed inset-0 z-50"
    aria-modal="true"
    role="dialog"
>
    {{-- Overlay tendina --}}
    <div class="absolute inset-0 bg-black/40" @click="close()"></div>

    {{-- Contenitore modale --}}
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-2xl bg-white rounded-lg shadow-lg overflow-hidden">
            {{-- Header --}}
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <div>
                    <div class="text-sm text-gray-500">Abbina tessuto × colore al componente</div>
                    <div class="font-semibold">
                        <span x-text="code"></span>
                        <span class="text-gray-500">—</span>
                        <span x-text="description"></span>
                    </div>
                </div>
                <button class="text-gray-500 hover:text-gray-700" @click="close()" title="Chiudi">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-4 space-y-4">
                {{-- Selezione Tessuto / Colore --}}
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-6">
                        <label class="block text-xs text-gray-600 mb-1">Tessuto</label>
                        <select id="matching-modal-fabric" class="border rounded w-full px-2 py-1"
                                x-model.number="form.fabric_id"
                                @change="validatePair(); checkNameCoherence()">
                            <option :value="null">— seleziona —</option>
                            @foreach($fabrics as $f)
                                <option value="{{ $f->id }}">{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-6">
                        <label class="block text-xs text-gray-600 mb-1">Colore</label>
                        <select class="border rounded w-full px-2 py-1"
                                x-model.number="form.color_id"
                                @change="validatePair(); checkNameCoherence()">
                            <option :value="null">— seleziona —</option>
                            @foreach($colors as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Stato coppia / Avvisi STRICT --}}
                <template x-if="pairStatus === 'empty'">
                    <div class="text-sm text-gray-600">
                        Seleziona un tessuto e un colore per verificare la coppia.
                    </div>
                </template>

                <template x-if="pairStatus === 'ok'">
                    <div class="text-sm px-3 py-2 rounded bg-green-50 text-green-800 border border-green-200">
                        <i class="fa-solid fa-circle-check mr-1"></i>
                        Coppia disponibile. Nessun altro componente usa questa coppia.
                    </div>
                </template>

                <template x-if="pairStatus === 'conflict'">
                    <div class="text-sm px-3 py-2 rounded bg-red-50 text-red-800 border border-red-200">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                        <strong>Conflitto:</strong> la coppia selezionata è già associata allo SKU
                        <span class="font-mono" x-text="conflictSkuCode"></span>
                        (ID <span x-text="conflictComponentId"></span>).
                        Scegli un’altra combinazione.
                    </div>
                </template>

                <template x-if="pairStatus === 'same'">
                    <div class="text-sm px-3 py-2 rounded bg-blue-50 text-blue-800 border border-blue-200">
                        <i class="fa-solid fa-info-circle mr-1"></i>
                        Questo componente è già associato alla coppia selezionata.
                    </div>
                </template>

                {{-- ⚠️ NUOVO: Coerenza descrizione ↔ scelta (non bloccante) --}}
                <template x-if="nameCoherence.status !== 'ok'">
                    <div class="text-sm px-3 py-2 rounded border"
                         :class="nameCoherence.status === 'warning' ? 'bg-orange-50 text-orange-800 border-orange-200' : 'bg-blue-50 text-blue-800 border-blue-200'">
                        <i class="fa-solid" :class="nameCoherence.status === 'warning' ? 'fa-triangle-exclamation' : 'fa-info-circle'"></i>
                        <span class="ml-1" x-text="nameCoherence.message"></span>
                    </div>
                </template>

                {{-- Errori server --}}
                <template x-if="serverError">
                    <div class="text-sm px-3 py-2 rounded bg-red-50 text-red-800 border border-red-200">
                        <i class="fa-solid fa-circle-xmark mr-1"></i>
                        <span x-text="serverError"></span>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="px-4 py-3 border-t flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    Regola: una sola riga TESSU può usare la stessa coppia tessuto×colore.
                </div>
                <div class="flex items-center gap-2">
                    <button class="px-3 py-1 rounded bg-gray-100" @click="close()">Chiudi</button>
                    <button class="px-3 py-1 rounded bg-emerald-600 text-white disabled:opacity-50"
                            :disabled="!canSave()"
                            @click="save()">
                        Salva abbinamento
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- JSON per Alpine: matrice e alias --}}
    <script type="application/json" id="matrix-json">{!! json_encode($matrix, JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/json" id="alias-fabrics-json">{!! json_encode($fabricAliases, JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/json" id="alias-colors-json">{!! json_encode($colorAliases, JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/json" id="ambiguous-colors-json">{!! json_encode($ambiguousColorTerms, JSON_UNESCAPED_UNICODE) !!}</script>

   {{-- JS Alpine --}}
    <script>
        function variableMatchingModal() {
            return {
                openModal: false,
                componentId: null,
                code: '',
                description: '',
                form: { fabric_id: null, color_id: null },
                pairStatus: 'empty', 
                conflictComponentId: null, 
                conflictSkuCode: '',
                serverError: '',
                nameCoherence: { status: 'ok', message: '' },

                open(payload) {
                    this.serverError = '';
                    this.nameCoherence = { status:'ok', message:'' };
                    this.componentId = payload.componentId ?? null;
                    this.code        = payload.code ?? '';
                    this.description = payload.description ?? '';
                    this.form.fabric_id = payload.fabricId ?? null;
                    this.form.color_id  = payload.colorId ?? null;
                    this.validatePair();
                    this.checkNameCoherence();
                    this.openModal = true;
                    setTimeout(() => {
                        const el = document.querySelector('#matching-modal-fabric');
                        if (el) el.focus();
                    }, 50);
                },
                close() { this.openModal = false; },

                // --- Helpers JSON scopes ---
                matrix() { return readJson('#matrix-json'); },
                fabricAliases() { return readJson('#alias-fabrics-json'); },
                colorAliases() { return readJson('#alias-colors-json'); },
                ambiguousColors() { return readJson('#ambiguous-colors-json'); },

                validatePair() {
                    this.serverError = '';
                    this.pairStatus = 'empty';
                    this.conflictComponentId = null;
                    this.conflictSkuCode     = '';

                    const fid = this.form.fabric_id;
                    const cid = this.form.color_id;
                    if (!fid || !cid) return;

                    const cell = (this.matrix()[fid] || {})[cid] || null;
                    if (!cell) { this.pairStatus = 'ok'; return; }
                    if (parseInt(cell.id, 10) === parseInt(this.componentId, 10)) this.pairStatus = 'same';
                    else {
                        this.pairStatus = 'conflict';
                        this.conflictComponentId = cell.id;
                        this.conflictSkuCode     = cell.code;
                    }
                },

                // --- coerenza descrizione ↔ scelta (solo descrizione) ---
                checkNameCoherence() {
                    // Normalizza descrizione
                    const text = norm(this.description || '');
                    if (!text) { this.nameCoherence = {status:'ok', message:'Descrizione neutra.'}; return; }

                    const fid = this.form.fabric_id, cid = this.form.color_id;
                    const fAliases = this.fabricAliases() || {};
                    const cAliases = this.colorAliases() || {};
                    const ambi     = this.ambiguousColors() || [];

                    // Trova match nella descrizione
                    const foundF = scanMatches(text, fAliases); // { id: [alias...] }
                    const foundC = scanMatches(text, cAliases);
                    const foundA = scanAmbiguous(text, ambi);   // [term...]

                    // Se manca una delle scelte non valutiamo conflitto (mostriamo solo info)
                    if (!fid || !cid) {
                        if (Object.keys(foundF).length || Object.keys(foundC).length || foundA.length) {
                            this.nameCoherence = {status:'info', message:'La descrizione suggerisce materiale/colore (completa la selezione).'};
                        } else {
                            this.nameCoherence = {status:'ok', message:''};
                        }
                        return;
                    }

                    const fabricConflict = hasConflict(foundF, parseInt(fid,10));
                    const colorConflict  = hasConflict(foundC, parseInt(cid,10));
                    if (fabricConflict || colorConflict) {
                        this.nameCoherence = {status:'warning', message:'La descrizione suggerisce un tessuto/colore diverso dalla scelta.'};
                        return;
                    }

                    const mentionsFabric = Object.keys(foundF).length > 0;
                    const mentionsColor  = Object.keys(foundC).length > 0;
                    if ((mentionsFabric ^ mentionsColor) || (!mentionsFabric && !mentionsColor && foundA.length)) {
                        this.nameCoherence = {status:'info', message: foundA.length ? 'La descrizione contiene termini ambigui.' : 'La descrizione cita solo tessuto o solo colore.'};
                        return;
                    }

                    this.nameCoherence = {status:'ok', message:''};
                },

                canSave() {
                    // Possiamo salvare se ho scelto entrambi i valori e non c'è conflitto.
                    return (this.form.fabric_id && this.form.color_id && this.pairStatus !== 'conflict');
                },

                async save() {
                    this.serverError = '';
                    if (!this.canSave()) return;

                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    if (!token) {
                        this.serverError = 'Token CSRF mancante.';
                        return;
                    }

                    // Endpoint: POST /variables/{component}/mapping
                    const url = `/variables/${this.componentId}/mapping`;

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({
                                fabric_id: this.form.fabric_id,
                                color_id:  this.form.color_id,
                            }),
                        });

                        const json = await res.json();

                        if (!res.ok || json?.ok === false) {
                            this.serverError = json?.message || 'Errore durante il salvataggio.';
                            return;
                        }
                        // Aggiorna matrice client-side e chiudi
                        const mx = this.matrix();
                        mx[this.form.fabric_id] = mx[this.form.fabric_id] || {};
                        mx[this.form.fabric_id][this.form.color_id] = { id: this.componentId, code: this.code, is_active: true };
                        document.getElementById('matrix-json').textContent = JSON.stringify(mx);
                        this.close();

                        // Ricarico la pagina per riflettere l'abbinamento nella tabella (semplice e robusto)
                        window.location.reload();
                    } catch (e) {
                        this.serverError = 'Errore di rete durante il salvataggio.';
                    }
                },
            }
        }

        // ---------- Utilità JS condivise ----------
        function readJson(id){ try{ return JSON.parse(document.querySelector(id)?.textContent || '{}'); }catch(e){ return {}; } }
        function norm(str){
            // minuscole + rimozione accenti + keep [a-z0-9 spazio]
            const s = (str || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
            return s.replace(/[^a-z0-9]+/g,' ').replace(/\s+/g,' ').trim();
        }
        function scanMatches(text, aliasesById){
            const hits = {};
            for (const [id, aliases] of Object.entries(aliasesById || {})) {
                for (const alias of (aliases || [])) {
                    if (!alias) continue;
                    const re = new RegExp(`\\b${escapeRegex(alias)}\\b`, 'u');
                    if (re.test(text)) { (hits[id] ||= []).push(alias); }
                }
            }
            return hits;
        }
        function scanAmbiguous(text, terms){
            const out = [];
            for (const t of (terms || [])) {
                if (!t) continue;
                const re = new RegExp(`\\b${escapeRegex(t)}\\b`, 'u');
                if (re.test(text)) out.push(t);
            }
            return out;
        }
        function hasConflict(foundById, mappedId){
            const ids = Object.keys(foundById || {}).map(n => parseInt(n,10));
            if (!ids.length) return false;
            return !(ids.length === 1 && ids[0] === parseInt(mappedId,10));
        }
        function escapeRegex(s){ return (s||'').replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
    </script>
</div>
