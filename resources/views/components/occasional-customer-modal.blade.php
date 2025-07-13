{{-- resources/views/components/occasional-customer-modal.blade.php --}}

{{-- =========================================================================
 |  Mini-modal   <x-occasional-customer-modal />
 |  Crea al volo un cliente occasionale (guest) e lo restituisce al padre.
 |  Stile, transizioni e dimensioni coerenti con il progetto Gestionale Divani.
 |  Dipendenze: Tailwind, Alpine, FontAwesome, CSRF meta tag.
 |  Permesso: orders.customer.create           (nessun altro è richiesto)
 |  Endpoint: POST /occasional-customers       (da implementare lato API)
 |  Risposta attesa: { id, company, vat_number, tax_code, address, ... }
 ========================================================================= --}}
<div
    x-data="occasionalCustomerModal()"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-60 flex items-start justify-center overflow-y-auto"
    x-on:open-occasional-customer-modal.window="openModal()"
>
    {{-- backdrop --}}
    <div class="absolute inset-0 bg-black/60" @click="close()"></div>

    {{-- dialog --}}
    <div
        class="relative bg-white dark:bg-gray-900 rounded-lg shadow-lg w-full max-w-md p-4 my-4 mx-4"
        x-transition.scale
    >
        {{-- header --}}
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-lg font-semibold leading-tight">Nuovo cliente occasionale</h3>
            <button @click="close()" class="text-gray-500 hover:text-gray-800">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- form --}}
        <form @submit.prevent="save()" class="space-y-4">

            {{-- ragione sociale --}}
            <div>
                <label class="block text-sm font-medium">Nome / Ragione sociale</label>
                <input type="text" x-model="form.company" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100" required>
            </div>

            {{-- P.IVA / C.F. --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium">Partita IVA</label>
                    <input type="text" x-model="form.vat_number" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium">Codice Fiscale</label>
                    <input type="text" x-model="form.tax_code" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>
            </div>

            {{-- indirizzo --}}
            <div>
                <label class="block text-sm font-medium">Indirizzo</label>
                <input type="text" x-model="form.address" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium">CAP</label>
                    <input type="text" x-model="form.postal_code" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium">Città</label>
                    <input type="text" x-model="form.city" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium">Prov.</label>
                    <input type="text" x-model="form.province" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>
            </div>

            {{-- contatti --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium">E-mail</label>
                    <input type="email" x-model="form.email" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium">Telefono</label>
                    <input type="text" x-model="form.phone" class="mt-1 block w-full px-3 py-2 border rounded-md bg-gray-50 dark:bg-gray-700
                               text-sm text-gray-900 dark:text-gray-100">
                </div>
            </div>

            {{-- footer --}}
            <div class="pt-4 flex justify-end space-x-2">
                <button type="button" @click="close()" class="inline-flex items-center px-3 py-1.5 border rounded-md text-xs font-semibold
                            text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">Annulla</button>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-purple-600
                            rounded-md text-sm font-semibold text-white uppercase
                            hover:bg-purple-500"
                        :disabled="saving"
                        x-text="saving ? 'Salvataggio…' : 'Crea'">
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function occasionalCustomerModal() {
    return {
        open   : false,
        saving : false,
        form   : makeBlank(),

        openModal() {
            this.open = true;
            this.form = makeBlank();
        },
        close() { this.open = false; },

        async save() {
            if (!this.form.company.trim()) return alert('La ragione sociale è obbligatoria.');

            this.saving = true;
            try {
                const r = await fetch('/occasional-customers', {
                    method : 'POST',
                    headers : {
                        'Accept':'application/json',
                        'Content-Type':'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials : 'same-origin',
                    body : JSON.stringify(this.form)
                });
                const json = await r.json();
                if (!r.ok) throw json;

                /* ➜ notifica il modale ordine che il guest è pronto */
                window.dispatchEvent(new CustomEvent('guest-created', { detail: json }));
                this.close();
            } catch (e) {
                console.error(e);
                alert('Errore durante la creazione del cliente occasionale.');
            } finally {
                this.saving = false;
            }
        }
    };

    function makeBlank() {
        return {
            company     : '',
            vat_number  : '',
            tax_code    : '',
            address     : '',
            postal_code : '',
            city        : '',
            province    : '',
            country     : 'IT',
            email       : '',
            phone       : ''
        };
    }
}
</script>
@endpush
