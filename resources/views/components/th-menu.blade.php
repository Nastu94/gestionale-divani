@props([
    'field',               // nome colonna (es. 'company', 'code', ecc.)
    'label',               // etichetta visuale (es. 'Cliente', 'Codice')
    'sort',                // campo attualmente ordinato, passato da controller
    'dir',                 // direzione corrente ('asc'|'desc'), passato da controller
    'filters',             // array filtri attivi, passato da controller
    'filterable' => true,  // mostra o meno l'input di filtro
    'resetRoute' => null,  // rotta per azzerare tutto; se null usa route corrente
    'align'      => 'left',// 'left' o 'right' per allineamento dropdown
])

@php
    $hasSort    = request()->has('sort');
    $isSorted   = $hasSort && $sort === $field;
    $exceptKeys = ['page', "filter.$field", 'sort', 'dir'];
    $baseQuery  = collect(request()->query())->forget($exceptKeys)->all();
    $urlWith    = fn(array $extra) => url()->current() . '?' . http_build_query(array_replace_recursive($baseQuery, $extra));
@endphp

<th {{ $attributes->merge(['class' => 'px-6 py-2 text-left relative']) }}
    x-data="{
        open : false,
        updatePos() {
            const btn = $refs.btn, dd = $refs.dropdown;
            const r   = btn.getBoundingClientRect();

            // ▼ coord. verticali: sempre sotto la <th>
            dd.style.top = (r.bottom + window.scrollY) + 'px';

            if ('{{ $align }}' === 'right') {
                // allineato a destra: calcoliamo lo spazio dal bordo destro viewport
                dd.style.left  = 'auto';
                dd.style.right = (window.innerWidth - r.right - window.scrollX) + 'px';
            } else {
                dd.style.right = 'auto';
                dd.style.left  = (r.left + window.scrollX) + 'px';
            }
        },
        init() {
            /* ---------- sync su scroll / resize ---------- */
            const sync = () => { if (this.open) this.updatePos() };

            // 3° arg. = true ➜ fase capture: intercetta anche lo scroll dei container
            window.addEventListener('scroll',  sync, true);
            window.addEventListener('resize', sync);

            /* ---------- watchdog su open ---------- */
            this.$watch('open', val => {
                const dd = $refs.dropdown;
                if (!val) { dd.remove(); return; }   // chiuso → stacchiamo il nodo

                // append & posizione iniziale
                document.body.appendChild(dd);
                dd.style.position = 'absolute';
                dd.style.zIndex   = 9999;
                this.updatePos();
            });
        }
    }"
    :class="{ 'bg-gray-100': open }"
>
    {{-- Trigger button --}}
    <button x-ref="btn" @click="open = !open"
            class="flex items-center gap-1 hover:text-indigo-600 focus:outline-none uppercase tracking-wider">
        {{ $label }}
        @if ($isSorted)
            <i class="fas fa-sort-{{ $dir === 'asc' ? 'up' : 'down' }}"></i>
        @endif
    </button>

    {{-- Dropdown append to body for Alpine positioning --}}
    <div  x-show="open"
          x-ref="dropdown"
          @click.outside="open = false"
          wire:ignore
          x-transition.opacity
          style="display:none"          {{-- Alpine sostituisce display a runtime --}}
          class="w-48 bg-white rounded shadow text-sm">

        {{-- Sorting links --}}
        <a href="{{ $urlWith(['sort'=>$field,'dir'=>'asc']) }}"  class="block px-3 py-1 hover:bg-gray-100">
            <i class="fa-solid fa-arrow-up-short-wide mr-1"></i><b>Crescente</b>
        </a>
        <a href="{{ $urlWith(['sort'=>$field,'dir'=>'desc']) }}" class="block px-3 py-1 hover:bg-gray-100">
            <i class="fa-solid fa-arrow-down-wide-short mr-1"></i><b>Decrescente</b>
        </a>

        {{-- Filter block --}}
        @if($filterable)
            <div class="border-t my-1"></div>
            <form method="GET" action="{{ url()->current() }}" class="px-3 py-1 space-y-1 text-xs">
                {{-- keep other params --}}
                @foreach($baseQuery as $k => $v)
                    @if(is_array($v))
                        @foreach($v as $subk => $subv)
                            <input type="hidden" name="{{ $k }}[{{ $subk }}]" value="{{ $subv }}" />
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}" />
                    @endif
                @endforeach
                <input type="text" name="filter[{{ $field }}]" value="{{ $filters[$field] ?? '' }}"
                       placeholder="Filtra {{ strtolower($label) }}…"
                       class="w-full border rounded px-1 py-0.5 focus:ring-indigo-500 focus:border-indigo-500" />
                <button class="w-full text-indigo-600 hover:underline"><b>Applica</b></button>
            </form>
        @endif

        <div class="border-t my-1"></div>
        <a href="{{ $resetRoute ? route($resetRoute) : url()->current() }}"
           class="block px-3 py-1 text-red-600 hover:bg-gray-100"><b>Azzera filtri</b></a>
    </div>
</th>
