{{-- resources/views/components/new-fabric-modal.blade.php --}}
<div
    x-data="newFabricModal()"
    x-show="openModal"
    x-cloak
    class="fixed inset-0 z-50"
    aria-modal="true"
    role="dialog"
    {{-- Ascolta l’evento lanciato dal bottone nella index --}}
    @open-new-fabric-modal.window="open()"
>
    <div class="absolute inset-0 bg-black/40" @click="close()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="px-4 py-3 border-b flex items-center justify-between">
                <div class="font-semibold">Nuovo Tessuto</div>
                <button type="button" class="text-gray-500 hover:text-gray-700" @click="close()" title="Chiudi">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-4 space-y-4">
                {{-- Nome --}}
                <div>
                    <label class="block text-xs text-gray-600 mb-1">Nome *</label>
                    <input type="text" class="border rounded w-full px-2 py-1" x-model.trim="form.name" placeholder="Es. Lino">
                    <p class="text-xs text-gray-500 mt-1">Obbligatorio. Deve essere univoco.</p>
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
                    <button type="button" class="px-3 py-1 rounded bg-indigo-600 text-white disabled:opacity-50"
                            :disabled="!canSave()" @click="save()">
                        Salva
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function newFabricModal() {
            return {
                openModal: false,
                form: { name: '', active: true, markup_type: '', markup_value: null },
                serverError: '',
                okMsg: '',

                open() {
                    this.reset();
                    this.openModal = true;
                },
                close() { this.openModal = false; },

                reset() {
                    this.form = { name: '', active: true, markup_type: '', markup_value: null };
                    this.serverError = ''; this.okMsg = '';
                },

                canSave() {
                    return (this.form.name || '').trim().length > 0;
                },

                async save() {
                    this.serverError = ''; this.okMsg = '';
                    if (!this.canSave()) return;

                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    if (!token) { this.serverError = 'Token CSRF mancante.'; return; }

                    // Preparo payload: se non c'è markup_type, non mando markup_value
                    const payload = {
                        name: this.form.name,
                        active: !!this.form.active,
                    };
                    if (this.form.markup_type) {
                        payload.markup_type  = this.form.markup_type;
                        payload.markup_value = this.form.markup_value ?? 0;
                    }

                    try {
                        const res = await fetch(`{{ route('variables.fabrics.store') }}`, {
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
                            // Mostro il primo messaggio utile
                            this.serverError = json?.message
                                || (json?.errors ? Object.values(json.errors).flat()[0] : 'Errore di salvataggio.');
                            return;
                        }

                        this.okMsg = 'Tessuto creato con successo.';
                        // Reload per aggiornare select, filtri e matrice
                        window.location.reload();

                    } catch (e) {
                        this.serverError = 'Errore di rete durante il salvataggio.';
                    }
                },
            }
        }
    </script>
</div>
