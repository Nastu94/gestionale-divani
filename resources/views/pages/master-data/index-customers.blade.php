{{-- resources/views/pages/master-data/index-customers.blade.php --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">{{ __('Clienti') }}</h2>
            <x-dashboard-tiles />
        </div>
    </x-slot>

    <div class="py-6">
        <div x-data="customerCrud()" class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                {{-- Pulsante “Nuovo” --}}
                <div class="flex justify-end m-2 p-2">
                    <button 
                        @click="openCreate"
                        class="inline-flex items-center m-2 px-3 py-1.5 bg-purple-600 rounded-md text-xs font-semibold text-white uppercase
                            hover:bg-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-300 transition"
                    >
                        <i class="fas fa-plus mr-1"></i> Nuovo
                    </button>

                    {{-- Pulsante Estendi/Comprimi su tutta la tabella --}}
                    <button
                        type="button"
                        @click="extended = !extended"
                        class="inline-flex items-center m-2 px-3 py-1.5 bg-indigo-600 rounded-md text-xs font-semibold text-white uppercase
                            hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-900 transition"
                    >
                        <i class="fas p-1" :class="extended ? 'fa-compress' : 'fa-expand'"></i>
                        <span x-text="extended ? 'Comprimi tabella' : 'Estendi tabella'"></span>
                    </button>
                </div>

                {{-- Modale Create / Edit --}}
                <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black opacity-75" @click="showModal = false"></div>
                    <div class="relative z-10 w-full max-w-3xl">
                        <x-customer-create-modal :customers="$customers" />
                    </div>
                </div>             

                {{-- Tabella espandibile --}}
                <div class="overflow-x-auto p-4">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-300 dark:bg-gray-700">
                            <tr class="uppercase tracking-wider">
                                <th class="px-6 py-2 text-left">#</th>
                                <x-th-menu
                                    field="company"
                                    label="Cliente"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :filterable="true"
                                    reset-route="customers.index"
                                    align="left"
                                />
                                <th class="px-6 py-2 text-left">P.IVA</th>
                                <th class="px-6 py-2 text-left">CF</th>
                                <th class="px-6 py-2 text-left">Email</th>
                                <th class="px-6 py-2 text-left">Telefono</th>
                                <th class="px-6 py-2 text-center">Attivo</th>

                                {{-- Colonne indirizzi, visibili solo se extended --}}
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Indirizzo Fatturazione</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Indirizzo Spedizione</th>
                                <th x-show="extended" x-cloak class="px-6 py-2 text-left whitespace-nowrap">Altro Indirizzo</th>
                            </tr>
                        </thead>

                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($customers as $customer)
                                @php
                                    $canEdit   = auth()->user()->can('customers.update');
                                    $canDelete = auth()->user()->can('customers.delete');
                                    $canCrud   = $canEdit || $canDelete;
                                
                                    $b = $customer->addresses->firstWhere('type','billing');
                                    $s = $customer->addresses->firstWhere('type','shipping');
                                    $o = $customer->addresses->firstWhere('type','other');
                                @endphp

                                {{-- Riga principale --}}
                                <tr
                                    @if($canCrud)
                                        @click="openId = (openId === {{ $customer->id }} ? null : {{ $customer->id }})"
                                        class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                        :class="openId === {{ $customer->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                    @endif
                                >
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $loop->iteration + ($customers->currentPage()-1)*$customers->perPage() }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $customer->company }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $customer->vat_number ?? '—' }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $customer->tax_code ?? '—' }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $customer->email ?? '—' }}</td>
                                    <td class="px-6 py-2 whitespace-nowrap">{{ $customer->phone ?? '—' }}</td>
                                    <td class="px-6 py-2 text-center whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                            :class="{
                                                'bg-green-100 text-green-800': {{ $customer->is_active ? 'true' : 'false' }},
                                                'bg-red-100 text-red-800': {{ $customer->is_active ? 'false' : 'true' }}
                                            }"
                                        >
                                            {{ $customer->is_active ? 'Sì' : 'No' }}
                                        </span>
                                    </td>

                                    {{-- Colonne indirizzi, visibili solo se extended --}}
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">
                                        @if($b)
                                            {{ $b->address }}, {{ $b->city }}, {{ $b->country }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">
                                        @if($s)
                                            {{ $s->address }}, {{ $s->city }}, {{ $s->country }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td x-show="extended" x-cloak class="px-6 py-2 whitespace-nowrap">
                                        @if($o)
                                            {{ $o->address }}, {{ $o->city }}, {{ $o->country }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>

                                {{-- Riga espansa con Modifica / Elimina / Estendi --}}
                                @if($canCrud)
                                <tr x-show="openId === {{ $customer->id }}" x-cloak>
                                    <td
                                        :colspan="extended ? 11 : 8"
                                        class="px-6 py-3 bg-gray-200 dark:bg-gray-700"
                                    >
                                        <div class="flex items-center space-x-4 text-xs">
                                            @if($canEdit)
                                                <button
                                                    type="button"
                                                    @click='openEdit(@json($customer))'
                                                    class="inline-flex items-center hover:text-yellow-600"
                                                >
                                                    <i class="fas fa-pencil-alt mr-1"></i> Modifica
                                                </button>
                                            @endif

                                            @if($canDelete)
                                                <form
                                                    action="{{ route('customers.destroy', $customer) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Sei sicuro di voler eliminare questo cliente?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center hover:text-red-600">
                                                        <i class="fas fa-trash-alt mr-1"></i> Elimina
                                                    </button>
                                                </form>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginazione --}}
                <div class="mt-4 px-6 py-2">
                    {{ $customers->links('vendor.pagination.tailwind-compact') }}
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('customerCrud', () => ({
            // Modal
            showModal: false,
            mode: 'create',
            form: { 
                id: null, 
                company: '', 
                vat_number: '', 
                tax_code: '', 
                email: '', 
                phone: '', 
                is_active: true, 
                addresses: [] 
            },
            errors: {},

            // Per riga espansa e colonne aggiuntive
            openId: null,
            extended: false,

            openCreate() {
                this.resetForm();
                this.mode = 'create';
                this.showModal = true;
            },

            openEdit(customer) {
                this.mode = 'edit';
                this.form.id         = customer.id;
                this.form.company    = customer.company;
                this.form.vat_number = customer.vat_number ?? '';
                this.form.tax_code   = customer.tax_code   ?? '';
                this.form.email      = customer.email      ?? '';
                this.form.phone      = customer.phone      ?? '';
                this.form.is_active  = customer.is_active;
                this.form.addresses  = customer.addresses.map(a => ({
                    type:         a.type,
                    address:      a.address,
                    city:         a.city,
                    postal_code:  a.postal_code,
                    country:      a.country,
                }));
                this.errors = {};
                this.showModal = true;
            },

            resetForm() {
                this.form = { 
                    id: null, 
                    company: '', 
                    vat_number: '', 
                    tax_code: '', 
                    email: '', 
                    phone: '', 
                    is_active: true, 
                    addresses: [] 
                };
                this.errors = {};
            },

            validateCustomer() {
                this.errors = {};
                let valid = true;
                if (! this.form.company.trim()) {
                    this.errors.company = 'Il nome è obbligatorio.';
                    valid = false;
                }
                return valid;
            },
        }));
    });
    </script>
    @endpush
</x-app-layout>