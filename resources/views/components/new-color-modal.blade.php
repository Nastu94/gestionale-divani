{{-- resources/views/components/new-color-modal.blade.php --}}
<div
    x-data="newColorModal()"
    x-show="openModal"
    x-cloak
    class="fixed inset-0 z-50"
    aria-modal="true"
    role="dialog"
    @open-new-color-modal.window="open()"
>
    <div class="absolute inset-0 bg-black/40" @click="close()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <div class="font-semibold">Nuovo Colore</div>
                <button type="button" class="text-gray-500 hover:text-gray-700" @click="close()" title="Chiudi">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-4 space-y-4">
                {{-- Nome --}}
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Nome *</label>
                    <input type="text" class="border rounded w-full px-2 py-1"
                           x-model.trim="form.name" placeholder="Es. Grigio">
                    <p class="text-xs text-gray-500 mt-1">Obbligatorio. Deve essere univoco.</p>
                </div>

                {{-- HEX (testo) + Color Picker sincronizzati --}}
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Colore</label>

                    <div class="grid grid-cols-12 gap-2 items-center">
                        {{-- Campo testo HEX --}}
                        <div class="col-span-7">
                            <div class="flex items-center gap-2">
                                <input type="text"
                                       class="border rounded w-full px-2 py-1 font-mono"
                                       placeholder="#RRGGBB o #RGB"
                                       x-model.trim="form.hex"
                                       @input="onHexTextInput()">
                                <span class="inline-block w-6 h-6 rounded border"
                                      :style="previewStyle()"
                                      title="Anteprima"></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Puoi scrivere l’HEX (es. <code>#FFFFFF</code>) oppure usare il selettore a destra.
                            </p>
                        </div>

                        {{-- Color picker nativo --}}
                        <div class="col-span-5">
                            <div class="flex items-center gap-2">
                                <input type="color"
                                       x-ref="colorPicker"
                                       class="h-9 w-14 cursor-pointer border rounded"
                                       :value="pickerValue"
                                       @input="onPickerInput($event)">
                                <button type="button"
                                        class="px-2 py-1 text-xs rounded bg-gray-100"
                                        @click="clearHex()">
                                    Svuota
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Il selettore produce sempre formato <code>#RRGGBB</code>.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Attivo --}}
                <div>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" class="rounded" x-model="form.active">
                        <span class="text-sm">Attivo</span>
                    </label>
                </div>

                {{-- Maggiorazione (opzionale) --}}
                <div class="bg-gray-50 border rounded p-3">
                    <div class="grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-6">
                            <label class="block text-xs text-gray-600 mb-1">Tipo maggiorazione</label>
                            <select class="border rounded w-full px-2 py-1" x-model="form.markup_type">
                                <option value="">— Nessuna —</option>
                                <option value="fixed">Fissa (€)</option>
                                <option value="percent">Percentuale (%)</option>
                            </select>
                        </div>
                        <div class="col-span-6">
                            <label class="block text-xs text-gray-600 mb-1">Valore</label>
                            <input type="number" step="0.01" min="0" class="border rounded w-full px-2 py-1"
                                   x-model.number="form.markup_value" :disabled="!form.markup_type">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Facoltativo. Se il database non prevede questi campi, verranno ignorati.
                    </p>
                </div>

                {{-- Errori server --}}
                <template x-if="serverError">
                    <div class="text-sm px-3 py-2 rounded bg-red-50 text-red-800 border border-red-200">
                        <i class="fa-solid fa-circle-xmark mr-1"></i>
                        <span x-text="serverError"></span>
                    </div>
                </template>

                {{-- Esito --}}
                <template x-if="okMsg">
                    <div class="text-sm px-3 py-2 rounded bg-green-50 text-green-800 border border-green-200">
                        <i class="fa-solid fa-circle-check mr-1"></i>
                        <span x-text="okMsg"></span>
                    </div>
                </template>
            </div>

            <div class="px-4 py-3 border-t flex items-center justify-between">
                <div class="text-xs text-gray-500">I campi contrassegnati con * sono obbligatori.</div>
                <div class="flex items-center gap-2">
                    <button type="button" class="px-3 py-1 rounded bg-gray-100" @click="close()">Chiudi</button>
                    <button type="button" class="px-3 py-1 rounded bg-sky-600 text-white disabled:opacity-50"
                            :disabled="!canSave()" @click="save()">
                        Salva
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function newColorModal() {
            return {
                openModal: false,
                form: { name: '', hex: '', active: true, markup_type: '', markup_value: null },
                serverError: '',
                okMsg: '',
                // Valore attuale del color picker (sempre #RRGGBB quando presente)
                pickerValue: '#FFFFFF',

                open() { this.reset(); this.openModal = true; },
                close() { this.openModal = false; },

                reset() {
                    this.form = { name: '', hex: '', active: true, markup_type: '', markup_value: null };
                    this.serverError = ''; this.okMsg = '';
                    this.pickerValue = '#FFFFFF';
                    // allinea picker all'HEX se già presente (es. reopen)
                    this.syncPickerFromText();
                },

                canSave() { return (this.form.name || '').trim().length > 0; },

                // --- Sincronizzazione TESTO -> PICKER ---
                onHexTextInput() {
                    // Normalizza solo per preview/picker; la validazione completa resta al server
                    this.syncPickerFromText();
                },
                syncPickerFromText() {
                    const hex = this.normalizeHexOrNull(this.form.hex || '');
                    if (hex) {
                        this.pickerValue = hex;
                        if (this.$refs.colorPicker) this.$refs.colorPicker.value = hex;
                    }
                },

                // --- Sincronizzazione PICKER -> TESTO ---
                onPickerInput(e) {
                    const val = (e.target?.value || '').trim(); // già #RRGGBB
                    this.pickerValue = val;
                    this.form.hex = val; // copia nel testo
                },

                // Svuota HEX (testo e picker torna a default)
                clearHex() {
                    this.form.hex = '';
                    this.pickerValue = '#FFFFFF';
                    if (this.$refs.colorPicker) this.$refs.colorPicker.value = this.pickerValue;
                },

                // Anteprima riquadro accanto al testo
                previewStyle() {
                    const hex = this.normalizeHexOrNull(this.form.hex || '') || this.pickerValue;
                    return hex
                        ? `background:${hex}`
                        : 'background: repeating-conic-gradient(#eee 0% 25%, #fff 0% 50%) 50%/6px 6px';
                },

                // Normalizza #RGB → #RRGGBB; ritorna null se non valido
                normalizeHexOrNull(v) {
                    if (!v) return null;
                    let h = v.replace(/^#/, '').trim();
                    if (!/^[A-Fa-f0-9]{3}$/.test(h) && !/^[A-Fa-f0-9]{6}$/.test(h)) return null;
                    if (h.length === 3) h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2];
                    return '#'+h.toUpperCase();
                },

                async save() {
                    this.serverError = ''; this.okMsg = '';
                    if (!this.canSave()) return;

                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    if (!token) { this.serverError = 'Token CSRF mancante.'; return; }

                    const payload = { name: this.form.name, active: !!this.form.active };
                    // Inviamo l'HEX se presente (testo o picker lo tengono allineato)
                    if ((this.form.hex || '').trim()) payload.hex = this.form.hex.trim();
                    if (this.form.markup_type) {
                        payload.markup_type  = this.form.markup_type;
                        payload.markup_value = this.form.markup_value ?? 0;
                    }

                    try {
                        const res = await fetch(`{{ route('variables.colors.store') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type':'application/json',
                                'Accept':'application/json',
                                'X-CSRF-TOKEN': token,
                            },
                            body: JSON.stringify(payload),
                        });
                        const json = await res.json();

                        if (!res.ok || json?.ok === false) {
                            this.serverError = json?.message
                                || (json?.errors ? Object.values(json.errors).flat()[0] : 'Errore di salvataggio.');
                            return;
                        }

                        this.okMsg = 'Colore creato con successo.';
                        window.location.reload(); // aggiorna select, filtri e matrice
                    } catch (e) {
                        this.serverError = 'Errore di rete durante il salvataggio.';
                    }
                },
            }
        }
    </script>
</div>
