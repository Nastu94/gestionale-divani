{{-- resources/views/components/new-color-modal.blade.php --}}
<div
    x-data="newColorModal()"
    x-show="openModal"
    x-cloak
    class="fixed inset-0 z-50"
    aria-modal="true"
    role="dialog"
    @open-new-color-modal.window="openCreate()"
    @open-edit-color-modal.window="openEdit($event.detail)"
>
    <div class="absolute inset-0 bg-black/40" @click="close()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <div class="font-semibold" x-text="isEdit ? 'Modifica Colore' : 'Nuovo Colore'"></div>
                <button type="button" class="text-gray-500 hover:text-gray-700" @click="close()" title="Chiudi">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-4 space-y-4">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Nome *</label>
                    <input type="text" class="border rounded w-full px-2 py-1"
                           x-model.trim="form.name" placeholder="Es. Grigio">
                </div>

                {{-- HEX + Picker sincronizzati --}}
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Colore (HEX)</label>
                    <div class="grid grid-cols-12 gap-2 items-center">
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
                            <p class="text-xs text-gray-500 mt-1">Scrivi l’HEX o usa il selettore.</p>
                        </div>
                        <div class="col-span-5">
                            <div class="flex items-center gap-2">
                                <input type="color"
                                       x-ref="colorPicker"
                                       class="h-9 w-14 cursor-pointer border rounded"
                                       :value="pickerValue"
                                       @input="onPickerInput($event)">
                                <button type="button" class="px-2 py-1 text-xs rounded bg-gray-100" @click="clearHex()">
                                    Svuota
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" class="rounded" x-model="form.active">
                        <span class="text-sm">Attivo</span>
                    </label>
                </div>

                <div class="bg-gray-50 border rounded p-3">
                    <div class="grid grid-cols-12 gap-2 items-end">
                        <div class="col-span-6">
                            <label class="block text-xs text-gray-600 mb-1">Tipo maggiorazione</label>
                            <select class="border rounded w-full px-2 py-1" x-model="form.surcharge_type">
                                <option value="">— Nessuna —</option>
                                <option value="fixed">Fissa (€)</option>
                                <option value="percent">Percentuale (%)</option>
                            </select>
                        </div>
                        <div class="col-span-6">
                            <label class="block text-xs text-gray-600 mb-1">Valore</label>
                            <input type="number" step="0.01" min="0" class="border rounded w-full px-2 py-1"
                                   x-model.number="form.surcharge_value" :disabled="!form.surcharge_type">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Facoltativo. Ignorato se le colonne non esistono in DB.</p>
                </div>

                <template x-if="serverError">
                    <div class="text-sm px-3 py-2 rounded bg-red-50 text-red-800 border border-red-200">
                        <i class="fa-solid fa-circle-xmark mr-1"></i>
                        <span x-text="serverError"></span>
                    </div>
                </template>
            </div>

            <div class="px-4 py-3 border-t flex items-center justify-between">
                <div class="text-xs text-gray-500">I campi * sono obbligatori.</div>
                <div class="flex items-center gap-2">
                    <button type="button" class="px-3 py-1 rounded bg-gray-100" @click="close()">Chiudi</button>
                    <button type="button"
                            class="px-3 py-1 rounded text-white disabled:opacity-50"
                            :class="isEdit ? 'bg-sky-700' : 'bg-sky-600'"
                            :disabled="!canSave()" @click="submit()"
                            x-text="isEdit ? 'Aggiorna' : 'Salva'">
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function newColorModal() {
            return {
                openModal: false,
                isEdit: false,
                editingId: null,
                form: { name: '', hex: '', active: true, surcharge_type: '', surcharge_value: null },
                serverError: '',
                pickerValue: '#FFFFFF',
                _previewHex: null,

                openCreate() {
                    this.reset();
                    this.isEdit = false; this.editingId = null;
                    this.openModal = true;
                },
                openEdit(payload) {
                    this.reset();
                    this.isEdit = true;
                    this.editingId = payload?.id ?? null;
                    this.form.name = payload?.name ?? '';
                    this.form.active = !!payload?.active;
                    this.form.surcharge_type = payload?.surcharge_type ?? '';
                    this.form.surcharge_value = payload?.surcharge_value ?? null;

                    // HEX + picker
                    this.form.hex = payload?.hex ?? '';
                    const hx = this.normalizeHexOrNull(this.form.hex || '');
                    this.pickerValue = hx || '#FFFFFF';
                    this.$nextTick(() => { if (this.$refs.colorPicker) this.$refs.colorPicker.value = this.pickerValue; });

                    this.openModal = true;
                },
                close() { this.openModal = false; },

                reset() {
                    this.serverError = '';
                    this.form = { name: '', hex: '', active: true, surcharge_type: '', surcharge_value: null };
                    this._previewHex = null;
                    this.pickerValue = '#FFFFFF';
                },

                canSave() { return (this.form.name || '').trim().length > 0; },

                onHexTextInput() { this.syncPickerFromText(); },
                syncPickerFromText() {
                    const hex = this.normalizeHexOrNull(this.form.hex || '');
                    if (hex) {
                        this.pickerValue = hex;
                        if (this.$refs.colorPicker) this.$refs.colorPicker.value = hex;
                    }
                },
                onPickerInput(e) {
                    const val = (e.target?.value || '').trim(); // #RRGGBB
                    this.pickerValue = val;
                    this.form.hex = val;
                },
                clearHex() {
                    this.form.hex = '';
                    this.pickerValue = '#FFFFFF';
                    if (this.$refs.colorPicker) this.$refs.colorPicker.value = this.pickerValue;
                },
                previewStyle() {
                    const hex = this.normalizeHexOrNull(this.form.hex || '') || this.pickerValue;
                    return hex ? `background:${hex}` : 'background: repeating-conic-gradient(#eee 0% 25%, #fff 0% 50%) 50%/6px 6px';
                },
                normalizeHexOrNull(v) {
                    if (!v) return null;
                    let h = v.replace(/^#/, '').trim();
                    if (!/^[A-Fa-f0-9]{3}$/.test(h) && !/^[A-Fa-f0-9]{6}$/.test(h)) return null;
                    if (h.length === 3) h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2];
                    return '#'+h.toUpperCase();
                },

                async submit() {
                    this.serverError = '';
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    if (!token) { this.serverError = 'Token CSRF mancante.'; return; }

                    const payload = { name: this.form.name, active: !!this.form.active };
                    if ((this.form.hex || '').trim()) payload.hex = this.form.hex.trim();
                    if (this.form.surcharge_type) {
                        payload.surcharge_type  = this.form.surcharge_type;
                        payload.surcharge_value = this.form.surcharge_value ?? 0;
                    }

                    const url = this.isEdit
                        ? `{{ url('variables/colors') }}/${this.editingId}`
                        : `{{ route('variables.colors.store') }}`;
                    const method = this.isEdit ? 'PUT' : 'POST';

                    try {
                        const res = await fetch(url, {
                            method,
                            headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': token },
                            body: JSON.stringify(payload),
                        });
                        const json = await res.json();
                        if (!res.ok || json?.ok === false) {
                            this.serverError = json?.message
                                || (json?.errors ? Object.values(json.errors).flat()[0] : 'Errore di salvataggio.');
                            return;
                        }
                        window.location.reload();
                    } catch {
                        this.serverError = 'Errore di rete durante il salvataggio.';
                    }
                },
            }
        }
    </script>
</div>
