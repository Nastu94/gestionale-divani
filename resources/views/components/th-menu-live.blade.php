{{-- resources/views/components/th-menu-live.blade.php --}}

{{--
    Componente per la gestione di header di tabella con ordinamento e filtri.
    Utilizza Alpine.js per la logica interattiva e Livewire per la sincronizzazione dei dati.
--}}

@props([
    'field',           // es. customer
    'label',           // testo header
    'sort'       => null,
    'dir'        => 'asc',
    'filters'    => [],
    'filterable' => true,
    'align'      => 'left',
    'page'       => 1,
])

@php  $alignClass = $align === 'right' ? 'right-0' : 'left-0'; @endphp

<th {{ $attributes->merge(['class'=>'px-6 py-2 text-left relative']) }}
    x-data="(() => ({
        /* Livewire ⇄ Alpine */
        {{-- x-th-menu-live.blade.php – init() Alpine --}}
        filter : @entangle('filters.' . $field).defer,
        sort   : @entangle('sort').live,
        dir    : @entangle('dir').live,

        open    : false,
        top     : null,   // coordinate runtime
        left    : null,
        right   : null,   // servirà per align = 'right'

        /* ---------- helpers ---------- */
        /** calcola le coordinate del trigger */
        updatePos() {
            const r = this.$refs.trigger.getBoundingClientRect();
            this.top = r.bottom + window.scrollY;
            if ('{{ $align }}' === 'right') {
                this.right = window.innerWidth - r.right - window.scrollX;
                this.left  = null;
            } else {
                this.left  = r.left + window.scrollX;
                this.right = null;
            }
        },

        /** apre o chiude il menu */
        toggle() {
            if (this.open) { this.open = false; return; }
            window.dispatchEvent(new Event('close-th-menus'));
            this.updatePos();               // ① posizione iniziale
            this.open = true;
        },

        init() {
            /* chiudi se altri menu si aprono */
            window.addEventListener('close-th-menus', () => this.open = false);

            /* ri-allinea durante scroll/resize (capture=true → becca anche
               lo scroll del wrapper overflow-auto)                     */
            const sync = () => { if (this.open) this.updatePos(); };
            window.addEventListener('scroll',  sync, true);
            window.addEventListener('resize', sync);
        },

        sortAsc(){  this.sort = '{{ $field }}'; this.dir = 'asc';  this.open = false },
        sortDesc(){ this.sort = '{{ $field }}'; this.dir = 'desc'; this.open = false },

        /* ---------- FILTRO ---------- */
        apply(){                          // clic su “Applica”
            $wire.set('filters.{{ $field }}', this.filter);
            this.open = false;
        },
        clear(){                          // clic su “Azzera filtri”
            this.filter = '';                                     // reset locale
            this.sort = '';
            this.dir = 'asc';
            $wire.set('filters.{{ $field }}', '');               // ③ invia reset
            this.open = false;
        },
    }))()"
    @keydown.escape.window="open = false"
    :class="open && 'bg-gray-100 dark:bg-gray-700'"
>
    {{-- ---------- TRIGGER ---------- --}}
    <button  type="button"
             x-ref="trigger"             {{-- serve per le coordinate --}}
             @click.prevent="toggle"
             class="flex items-center gap-1 hover:text-indigo-600 uppercase tracking-wider">
        {{ $label }}
        <template x-if="sort === '{{ $field }}'">
            <i :class="dir === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'"></i>
        </template>
    </button>

    {{-- ---------- DROPDOWN con TELEPORT ---------- --}}
    <template x-teleport="#portal-target">
        <div x-show="open"
             x-transition.opacity
             x-cloak
             x-bind:style="{
                 position: 'fixed',
                 top  : top  + 'px',
                 left : left  !== null ? left  + 'px' : null,
                 right: right !== null ? right + 'px' : null
             }"
             @click.outside="open = false"
             class="w-48 z-50 bg-white dark:bg-gray-800 rounded shadow
                    text-sm divide-y divide-gray-200 dark:divide-gray-700">

            {{-- ordinamento --}}
            <div class="py-1">
                <button type="button" @click.prevent="sortAsc"
                        class="w-full text-left px-3 py-1 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fa-solid fa-arrow-up-short-wide mr-1"></i><b>Crescente</b>
                </button>
                <button type="button" @click.prevent="sortDesc"
                        class="w-full text-left px-3 py-1 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fa-solid fa-arrow-down-wide-short mr-1"></i><b>Decrescente</b>
                </button>
            </div>

            {{-- filtro testo --}}
            @if ($filterable)
                <div class="py-1">
                    <div class="px-3 py-1 text-xs">
                        <input x-model.defer="filter"
                               type="text"
                               placeholder="Filtra {{ strtolower($label) }}…"
                               class="w-full border rounded px-1 py-0.5
                                      focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button type="button" @click.prevent="apply"
                            class="w-full text-left px-3 py-1 text-indigo-600 hover:underline text-xs">
                        <b>Applica</b>
                    </button>
                </div>
            @endif

            {{-- azzera --}}
            <div class="py-1">
                <button type="button" @click.prevent="clear"
                        class="w-full text-left px-3 py-1 text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-xs">
                    <b>Azzera filtri</b>
                </button>
            </div>
        </div>
    </template>
</th>
