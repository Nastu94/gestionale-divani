{{-- resources/views/components/create-missing-tessu-components-modal.blade.php --}}
@php
    /** @var \Illuminate\Support\Collection $fabrics */
    /** @var \Illuminate\Support\Collection $colors */
    /** @var array $matrix */
@endphp

<div
    x-data="createMissingTessuComponents()"
    x-show="openModal"
    x-cloak
    class="fixed inset-0 z-50"
    aria-modal="true"
    role="dialog"
    {{-- Ascolta l’evento lanciato dal pulsante nella index --}}
    @open-cmc-modal.window="open()"
>
    <div class="absolute inset-0 bg-black/40" @click="close()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-4xl bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <div class="font-semibold">Crea componenti TESSU mancanti</div>
                <button type="button" class="text-gray-500 hover:text-gray-700" @click="close()" title="Chiudi">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-4 space-y-4">
                {{-- Pattern descrizione --}}
                <div class="bg-gray-50 border rounded p-3">
                    <label class="block text-xs text-gray-600 mb-1">
                        Pattern descrizione (usa <code>:fabric</code> e <code>:color</code>)
                    </label>
                    <input type="text" class="border rounded w-full px-2 py-1"
                           x-model="descriptionPattern"
                           placeholder="Tessuto :fabric :color">
                    <div class="text-xs text-gray-500 mt-1">
                        Esempio: “Rivestimento :fabric colore :color” → “Rivestimento Lino colore Verde”
                    </div>
                </div>

                {{-- ✅ Seleziona tutti / Deseleziona tutti --}}
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" class="rounded"
                               x-model="selectAllChecked"
                               @change="selectAllChanged()"
                               :disabled="totalMissing === 0">
                        <span class="text-sm">Seleziona tutti (celle N/D)</span>
                    </label>
                    <span class="text-xs text-gray-500"
                          x-text="totalMissing > 0 ? `Celle mancanti: ${totalMissing}` : 'Nessuna cella mancante'"></span>
                </div>

                {{-- Matrice selezionabile: mostra SOLO le celle mancanti (N/D) --}}
                <div class="overflow-auto border rounded">
                    <table class="text-xs min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="p-2 border">Tessuto \ Colore</th>
                                @foreach($colors as $color)
                                    <th class="p-2 border whitespace-nowrap">
                                        @if($color->hex)
                                            <span class="inline-block w-3 h-3 rounded border align-middle" style="background: {{ $color->hex }}"></span>
                                        @endif
                                        {{ $color->name }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fabrics as $fabric)
                                <tr>
                                    <td class="p-2 border font-medium whitespace-nowrap">{{ $fabric->name }}</td>
                                    @foreach($colors as $color)
                                        @php $cell = $matrix[$fabric->id][$color->id] ?? null; @endphp
                                        <td class="p-2 border text-center">
                                            @if($cell)
                                                {{-- Già esistente: solo badge informativo --}}
                                                <span class="inline-block px-2 py-0.5 text-green-700 bg-green-100 rounded"
                                                      title="Esiste: {{ $cell['code'] }}">
                                                    {{ $cell['code'] }}
                                                </span>
                                            @else
                                                {{-- Mancante: checkbox selezionabile --}}
                                                <label class="inline-flex items-center gap-1 cursor-pointer">
                                                    <input type="checkbox"
                                                           class="rounded"
                                                           {{-- data-* per la selezione massiva via JS --}}
                                                           data-missing-pair
                                                           data-key="{{ $fabric->id }}-{{ $color->id }}"
                                                           data-fabric-id="{{ $fabric->id }}"
                                                           data-color-id="{{ $color->id }}"
                                                           @change="togglePair({{ $fabric->id }}, {{ $color->id }}); updateSelectAllState()">
                                                    <span class="inline-block px-2 py-0.5 text-red-700 bg-red-100 rounded"
                                                          title="Mancante">
                                                        N/D
                                                    </span>
                                                </label>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Esito / errori --}}
                <template x-if="serverError">
                    <div class="text-sm px-3 py-2 rounded bg-red-50 text-red-800 border border-red-200">
                        <i class="fa-solid fa-circle-xmark mr-1"></i>
                        <span x-text="serverError"></span>
                    </div>
                </template>
                <template x-if="createdCount > 0">
                    <div class="text-sm px-3 py-2 rounded bg-green-50 text-green-800 border border-green-200">
                        <i class="fa-solid fa-circle-check mr-1"></i>
                        Creati <strong x-text="createdCount"></strong> componenti TESSU.
                    </div>
                </template>
            </div>

            <div class="px-4 py-3 border-t flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    Seleziona le celle "N/D" per generare gli SKU TESSU mancanti.
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="px-3 py-1 rounded bg-gray-100" @click="close()">Chiudi</button>
                    <button type="button" class="px-3 py-1 rounded bg-emerald-600 text-white disabled:opacity-50"
                            :disabled="pairs.length === 0"
                            @click="submit()">
                        Crea componenti
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- JSON sorgenti per Alpine (opzionali per altre funzioni) --}}
    <script type="application/json" id="cmc-fabrics-json">{!! json_encode($fabrics->pluck('name','id'), JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/json" id="cmc-colors-json">{!! json_encode($colors->pluck('name','id'), JSON_UNESCAPED_UNICODE) !!}</script>

    <script>
        /**
         * Logica AlpineJS per la modale "Crea componenti TESSU mancanti".
         * - pairs: elenco delle coppie selezionate [{fabric_id, color_id}]
         * - selectAllChecked: stato della checkbox "Seleziona tutti"
         * - totalMissing: numero totale di celle N/D presenti nella tabella
         */
        function createMissingTessuComponents() {
            return {
                openModal: false,
                pairs: [], // [{fabric_id, color_id}]
                descriptionPattern: 'Tessuto :fabric :color',
                serverError: '',
                createdCount: 0,
                selectAllChecked: false,
                totalMissing: 0,

                // Apertura/chiusura
                open() {
                    this.serverError = '';
                    this.createdCount = 0;
                    this.pairs = [];
                    this.selectAllChecked = false;
                    this.openModal = true;

                    // Dopo il render, conteggia le celle mancanti per abilitare "Seleziona tutti"
                    this.$nextTick(() => this.updateSelectAllState());
                },
                close() { this.openModal = false; },

                // Helpers gestione pairs
                hasPair(fid, cid) {
                    return this.pairs.some(p => p.fabric_id === fid && p.color_id === cid);
                },
                addPair(fid, cid) {
                    if (!this.hasPair(fid, cid)) this.pairs.push({ fabric_id: fid, color_id: cid });
                },
                removePair(fid, cid) {
                    this.pairs = this.pairs.filter(p => !(p.fabric_id === fid && p.color_id === cid));
                },

                // Toggle singolo checkbox N/D
                togglePair(fid, cid) {
                    if (this.hasPair(fid, cid)) this.removePair(fid, cid);
                    else this.addPair(fid, cid);
                },

                // Aggiorna lo stato della checkbox "Seleziona tutti"
                updateSelectAllState() {
                    const inputs = document.querySelectorAll('input[data-missing-pair]');
                    this.totalMissing = inputs.length;
                    this.selectAllChecked = (this.totalMissing > 0 && this.pairs.length === this.totalMissing);
                },

                // Cambio stato "Seleziona tutti"
                selectAllChanged() {
                    const inputs = document.querySelectorAll('input[data-missing-pair]');
                    if (this.selectAllChecked) {
                        // seleziona tutte le celle mancanti
                        inputs.forEach(el => {
                            el.checked = true;
                            const fid = parseInt(el.dataset.fabricId, 10);
                            const cid = parseInt(el.dataset.colorId, 10);
                            this.addPair(fid, cid);
                        });
                    } else {
                        // deseleziona tutte
                        inputs.forEach(el => { el.checked = false; });
                        this.pairs = [];
                    }
                    this.updateSelectAllState();
                },

                // Submit
                async submit() {
                    this.serverError = '';
                    if (this.pairs.length === 0) return;

                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    if (!token) { this.serverError = 'Token CSRF mancante.'; return; }

                    try {
                        const res = await fetch(`{{ route('variables.components.missing') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type':'application/json',
                                'Accept':'application/json',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify({
                                pairs: this.pairs,
                                description_pattern: this.descriptionPattern || 'Tessuto :fabric :color',
                            }),
                        });
                        const json = await res.json();
                        if (!res.ok || json?.ok === false) {
                            this.serverError = json?.message || 'Errore durante la creazione.';
                            return;
                        }
                        this.createdCount = json?.created_count || 0;

                        // Ricarico per aggiornare matrice e tabella
                        window.location.reload();
                    } catch (e) {
                        this.serverError = 'Errore di rete durante la creazione.';
                    }
                },
            }
        }
    </script>
</div>
