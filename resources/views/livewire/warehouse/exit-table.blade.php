{{-- resources/views/livewire/warehouse/exit-table.blade.php --}}
<div>

    {{-- ╔═════════════ KPI CARDS ═════════════╗ --}}
    <div class="py-2">
        @php
            $fasi = [
                0 => ['txt' => 'Inserito'      , 'icon' => 'fa-upload'          , 'bg' => 'bg-gray-300 dark:bg-gray-700'],
                1 => ['txt' => 'Struttura'     , 'icon' => 'fa-hammer'          , 'bg' => 'bg-yellow-100 dark:bg-yellow-700'],
                2 => ['txt' => 'Imbottitura'   , 'icon' => 'fa-feather'         , 'bg' => 'bg-green-100 dark:bg-green-700'],
                3 => ['txt' => 'Rivestimento'  , 'icon' => 'fa-couch'           , 'bg' => 'bg-blue-100  dark:bg-blue-700'],
                4 => ['txt' => 'Assemblaggio'  , 'icon' => 'fa-screwdriver-wrench', 'bg' => 'bg-indigo-100 dark:bg-indigo-700'],
                5 => ['txt' => 'Finitura'      , 'icon' => 'fa-brush'           , 'bg' => 'bg-purple-100 dark:bg-purple-700'],
                6 => ['txt' => 'Spedizione'    , 'icon' => 'fa-truck'           , 'bg' => 'bg-red-100   dark:bg-red-700'],
            ];
        @endphp

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2">
            @foreach ($fasi as $idx => $d)
                @php $sel = $phase === $idx; @endphp
                <div  tabindex="0"
                    wire:click="$set('phase', {{ $idx }})"
                    @keydown.enter.prevent="$set('phase', {{ $idx }})"
                    class="cursor-pointer rounded p-3 flex flex-col items-center justify-center
                            {{ $d['bg'] }}
                            {{ $sel ? 'ring-2 ring-indigo-500 scale-105 transition transform' : '' }}">
                    <i class="fas {{ $d['icon'] }} text-xl mb-1"></i>
                    <span class="text-xs font-semibold">{{ $d['txt'] }}</span>
                    <span class="text-lg font-bold">{{ $kpiCounts[$idx] ?? 0 }}</span>
                </div>
            @endforeach
        </div>
    </div>
    
    {{-- ╔═════════════ FLASH MESSAGES ════════════╗ --}}
    <x-flash />

    {{-- ╔═════════════ TABELLONE ═════════════╗ --}}
    <div class="py-6" x-data="exitCrud()" @open-row.window="openId = ($event.detail === openId ? null : $event.detail)" @close-row.window="openId = null">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="p-4 overflow-x-auto">
                    <table class="table-auto min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        {{-- HEAD --}}
                        <thead class="bg-gray-300 dark:bg-gray-700 uppercase tracking-wider">
                            <tr>

                                {{-- CLIENTE --}}
                                <x-th-menu-live
                                    field="customer"
                                    label="Cliente"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :align="'left'"
                                />

                                {{-- NUMERO ORDINE --}}
                                <x-th-menu-live
                                    field="order_number"
                                    label="Nr. Ordine"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :align="'left'"
                                />

                                {{-- PRODOTTO --}}
                                <x-th-menu-live
                                    field="product"
                                    label="Prodotto"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :align="'left'"
                                />

                                {{-- DATA ORDINE --}}
                                <x-th-menu-live
                                    field="order_date"
                                    label="Data ordine"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                />

                                {{-- DATA CONSEGNA --}}
                                <x-th-menu-live
                                    field="delivery_date"
                                    label="Consegna"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                />

                                {{-- VALORE € --}}
                                <x-th-menu-live
                                    field="value"
                                    label="Valore €"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                />

                                {{-- Q.TY FASE --}}
                                <x-th-menu-live
                                    field="qty_in_phase"
                                    label="Q.ty fase"
                                    :sort="$sort"
                                    :dir="$dir"
                                    :filters="$filters"
                                    :align="'right'"
                                />
                            </tr>
                        </thead>

                        {{-- BODY --}}
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($exitRows as $row)
                                @php
                                    // permessi grezzi
                                    $canAdvanceRaw  = auth()->user()->can('stock.exit');
                                    $canRollbackRaw = auth()->user()->can('orders.customer.rollback_item_phase');

                                    // logica di fase
                                    $canAdvance  = $canAdvanceRaw  && $phase < 6;   // no “Avanza” se già in Spedizione
                                    $canRollback = $canRollbackRaw && $phase > 0;   // no “Rollback” in Inserito
                                    $showDdT     =                ($phase == 6);    // DdT solo in Spedizione

                                    $canToggle   = $canAdvance || $canRollback || $showDdT;
                                @endphp

                                {{-- RIGA PRINCIPALE --}}
                                <tr  @if($canToggle)
                                         @click="$dispatch('open-row', {{ $row->id }})"
                                         class="cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700"
                                         :class="openId === {{ $row->id }} ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                     @endif>
                                    {{-- Cliente --}}
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ $row->customer ?? '—' }}
                                    </td>

                                    {{-- Nr. ordine --}}
                                    <td class="px-6 py-2 text-center">
                                        {{ $row->order_number ?? '—' }}
                                    </td>

                                    {{-- Prodotto (SKU - nome) --}}
                                    <td class="px-6 py-2 whitespace-nowrap"
                                        title="{{ $row->product_name }}">
                                        {{ $row->product_name ?? '—' }}
                                    </td>

                                    {{-- Data ordine / consegna --}}
                                    <td class="px-6 py-2  whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($row->order_date)->format('Y-m-d') ?? '—' }}
                                    </td>
                                    <td class="px-6 py-2 whitespace-nowrap">
                                        {{ \Carbon\Carbon::parse($row->delivery_date)->format('Y-m-d') ?? '—' }}
                                    </td>

                                    {{-- Valore € --}}
                                    <td class="px-6 py-2 text-right whitespace-nowrap">
                                        € {{ number_format($row->value, 2, ',', '.') }}
                                    </td>
                                    <td class="px-6 py-2 text-right">{{ $row->qty_in_phase }}</td>
                                </tr>

                                {{-- RIGA TOOLBAR --}}
                                @if($canToggle)
                                    <tr x-show="openId === {{ $row->id }}" x-cloak>
                                        <td :colspan="9" class="px-6 py-3 bg-gray-200 dark:bg-gray-700">
                                            <div class="flex items-center space-x-4 text-xs">
                                                {{-- ► Avanza fase (qty default 100 %) --}}
                                                @if($canAdvance)
                                                    <button type="button"
                                                            class="inline-flex items-center hover:text-green-700"
                                                            wire:click="openAdvance({{ $row->id }}, {{ $row->qty_in_phase }})">
                                                        <i class="fas fa-forward mr-1"></i> Avanza
                                                    </button>
                                                @endif

                                                {{-- ↶ Rollback --}}
                                                @if($canRollback)
                                                    <button  type="button" class="inline-flex items-center hover:text-amber-600"
                                                             @click.prevent="$wire.emit('open-rollback', {{ $row->id }}, {{ $row->qty_in_phase }})">
                                                        <i class="fas fa-undo mr-1"></i> Rollback
                                                    </button>
                                                @endif

                                                {{-- 📝 Note --}}
                                                <button type="button" class="inline-flex items-center hover:text-indigo-600"
                                                        @click.prevent="$wire.emit('open-note', {{ $row->id }})">
                                                    <i class="fas fa-sticky-note mr-1"></i> Note
                                                </button>

                                                {{-- 🖨 DdT --}}
                                                @if($showDdT)
                                                    <button  type="button" class="inline-flex items-center hover:text-purple-600"
                                                             @click.prevent="$wire.emit('print-ddt', {{ $row->id }})">
                                                        <i class="fas fa-print mr-1"></i> DdT
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- PAGINAZIONE --}}
                <div class="flex items-center justify-between px-6 py-2">
                    <div>
                        {{ $exitRows->links('vendor.pagination.tailwind-compact') }}
                    </div>
                    <div>
                        <label class="text-xs mr-1 p-1">Righe:</label>
                        <select wire:model="perPage" class="border rounded px-5 py-1 text-xs">
                            <option value="100">100</option>
                            <option value="250">250</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{--  Modal Avanza  ----------------------------------------------------}}
    <dialog  wire:ignore
            x-data="advanceModal()"
            x-init="
                dlg = $el;                   /*  inizializza subito  */
                window.addEventListener('show-adv-modal', e => open(e.detail))
            "
            @click.outside="close"           {{-- ora il listener globale è in x-init  --}}
            @keydown.escape.window="close"
            class="rounded-lg shadow-lg w-80 max-w-full p-5
                    bg-white dark:bg-gray-800 backdrop:bg-black/40">

        <h2 class="text-lg font-semibold mb-4">Avanza fase</h2>

        <form @submit.prevent="confirm" class="space-y-4">
            <div>
                <label class="block text-sm mb-1">
                    Quantità (max <span x-text="max"></span>)
                </label>

                <input type="number" step="1" min="1"
                    x-model.number="qty"
                    class="w-full border rounded px-2 py-1
                            focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="flex justify-end gap-2 text-sm">
                <button type="button" @click="close"
                        class="px-3 py-1 bg-gray-300 rounded">Annulla</button>
                <button type="submit"
                        class="px-3 py-1 bg-green-600 text-white rounded">
                    Conferma
                </button>
            </div>
        </form>
    </dialog>

</div>

@push('scripts')
    <script>
        function advanceModal () {
            return {
                dlg : null,          // settato in x-init
                compId : null,
                id  : null,
                max : 0,
                qty : 1,

                /* ▲ apre il dialog   -------------------------------------- */
                open (p) {
                    this.id  = p.id
                    this.max = p.maxQty
                    this.qty = p.defaultQty
                    /* fallback per browser senza <dialog> */
                    if (! this.dlg.showModal) {
                        this.dlg.setAttribute('open', '')
                    } else {
                        this.dlg.showModal()
                    }
                },

                /* ▲ conferma → chiama Livewire ----------------------------- */
                confirm () {
                    if (this.qty < 1 || this.qty > this.max) {
                        alert('Quantità non valida. Non può essere maggiore di' + this.max);
                        return;
                    }

                    const comp = Livewire.find(this.compId)
                    if (! comp) { console.error('Livewire component not found'); return }

                    // ①  aggiorna la property
                    comp.set('advQuantity', this.qty)
                        // ②  solo dopo chiama il metodo
                        .then(() => comp.call('confirmAdvance', this.qty));

                    this.close();
                },

                /* ▲ chiusura ---------------------------------------------- */
                close () { this.dlg.close ? this.dlg.close() : this.dlg.removeAttribute('open') },
                
                init () {
                    this.dlg    = this.$el
                    this.compId = this.$el.closest('[wire\\:id]').getAttribute('wire:id')  // ② salva id

                    /* fallback browser che non supportano <dialog>  */
                    if (! this.dlg.showModal) {
                        this.dlg.showModal = () => this.dlg.setAttribute('open', '')
                        this.dlg.close     = () => this.dlg.removeAttribute('open')
                    }

                    /* listener all’evento dispatched da Livewire             */
                    window.addEventListener('show-adv-modal', e => this.open(e.detail))
                },
            }
        }
    </script>
@endpush

