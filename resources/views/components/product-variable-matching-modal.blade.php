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
                        <select class="border rounded w-full px-2 py-1"
                                x-model.number="form.fabric_id"
                                @change="validatePair()">
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
                                @change="validatePair()">
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

                {{-- Note/Regole --}}
                <div class="text-xs text-gray-500">
                    Regola STRICT: ogni coppia tessuto×colore può essere usata da <strong>un solo</strong> componente TESSU.
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-4 py-3 border-t flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    Salvataggio disabilitato in questa fase (solo front-end).
                </div>
                <div class="flex items-center gap-2">
                    <button class="px-3 py-1 rounded bg-gray-100" @click="close()">Chiudi</button>
                    {{-- Il pulsante Salva resta disabilitato per ora (nessun controller agganciato) --}}
                    <button class="px-3 py-1 rounded bg-emerald-600 text-white opacity-60 cursor-not-allowed"
                            :title="saveTooltip()"
                            disabled>
                        Salva abbinamento
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Script Alpine del modale (solo client-side) --}}
    <script>
        function variableMatchingModal() {
            return {
                // Stato principale del modale
                openModal: false,

                // Dati del componente su cui sto lavorando
                componentId: null,
                code: '',
                description: '',

                // Form corrente
                form: {
                    fabric_id: null,
                    color_id:  null,
                },

                // Esito validazione coppia vs matrice globale
                pairStatus: 'empty', // empty | ok | conflict | same
                conflictComponentId: null,
                conflictSkuCode: '',

                // Apertura modale con payload inviato dal pulsante "Abbina"
                open(payload) {
                    this.componentId = payload.componentId ?? null;
                    this.code        = payload.code ?? '';
                    this.description = payload.description ?? '';

                    // Precompila con mapping attuale (se presente)
                    this.form.fabric_id = payload.fabricId ?? null;
                    this.form.color_id  = payload.colorId ?? null;

                    // Valida subito lo stato coppia
                    this.validatePair();

                    this.openModal = true;
                    // Focus gentile sul primo select
                    setTimeout(() => {
                        const el = document.querySelector('#matching-modal-fabric');
                        if (el) el.focus();
                    }, 50);
                },

                close() {
                    // Reset soft (manteniamo i dati in caso di riapertura su stessa riga)
                    this.openModal = false;
                },

                // Ricava dallo scope Blade la matrice globale (fabric×color → component)
                matrix() {
                    // Blade stampa la matrice come JSON embedded su data-attr dell'elemento <script> sotto
                    const el = document.getElementById('matrix-json');
                    if (!el) return {};
                    try { return JSON.parse(el.textContent || '{}'); } catch(e) { return {}; }
                },

                // Verifica la coppia selezionata contro la matrice:
                // - same: la coppia è già di questo componente
                // - conflict: la coppia è usata da un altro componente
                // - ok: libera
                // - empty: manca una delle due scelte
                validatePair() {
                    this.pairStatus = 'empty';
                    this.conflictComponentId = null;
                    this.conflictSkuCode     = '';

                    const fid = this.form.fabric_id;
                    const cid = this.form.color_id;
                    if (!fid || !cid) return;

                    const mx = this.matrix();
                    const row = mx[fid] || {};
                    const cell = row[cid] || null;

                    if (!cell) {
                        this.pairStatus = 'ok';
                        return;
                    }

                    // Se esiste una riga matrice, è già associata a un componente
                    if (parseInt(cell.id, 10) === parseInt(this.componentId, 10)) {
                        this.pairStatus = 'same';
                    } else {
                        this.pairStatus = 'conflict';
                        this.conflictComponentId = cell.id;
                        this.conflictSkuCode     = cell.code;
                    }
                },

                // Tooltip contestuale del pulsante Salva (disabilitato in questa fase)
                saveTooltip() {
                    if (this.pairStatus === 'empty') return 'Seleziona tessuto e colore';
                    if (this.pairStatus === 'conflict') return 'Coppia già in uso da un altro componente';
                    return 'Funzionalità di salvataggio verrà abilitata nei prossimi step';
                },
            }
        }
    </script>

    {{-- Dump "matrix" come JSON nascosto per consumo Alpine (no chiamate al controller) --}}
    <script type="application/json" id="matrix-json">
        {!! json_encode($matrix, JSON_UNESCAPED_UNICODE) !!}
    </script>
</div>
