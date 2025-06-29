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

<th {{ $attributes->merge(['class' => 'px-6 py-2 text-left relative']) }} x-data="{open:false}" :class="{'bg-gray-100': open}" >
    {{-- Trigger button --}}
    <button x-ref="btn" @click="open = !open" class="flex items-center gap-1 hover:text-indigo-600 focus:outline-none uppercase tracking-wider">
        {{ $label }}
        @if($isSorted)
            <i class="fas fa-sort-{{ $dir === 'asc' ? 'up' : 'down' }}"></i>
        @endif
    </button>

    {{-- Dropdown append to body for Alpine positioning --}}
    <div x-show="open" x-ref="dropdown" @click.outside="open=false" wire:ignore
         x-init="
            $watch('open', val => {
                 if (!val) return;
                 // append and position
                 const dd = $refs.dropdown;
                 document.body.appendChild(dd);
                 const rect = $refs.btn.getBoundingClientRect();
                 dd.style.position = 'absolute';
                 dd.style.zIndex = 9999;
                 dd.style.top = (rect.bottom + window.scrollY) + 'px';
                 const leftPos = '{{ $align }}' === 'right'
                     ? (rect.right - dd.offsetWidth + window.scrollX)
                     : (rect.left + window.scrollX);
                 dd.style.left = leftPos + 'px';
            });
         "
         style="display:none;"
         x-transition.opacity
         class="w-48 bg-white rounded shadow text-sm">

        {{-- Sorting links --}}
        <a href="{{ $urlWith(['sort'=>$field,'dir'=>'asc']) }}" class="block px-3 py-1 hover:bg-gray-100"><i class="fa-solid fa-arrow-up-short-wide"></i> <b>Crescente</b></a>
        <a href="{{ $urlWith(['sort'=>$field,'dir'=>'desc']) }}" class="block px-3 py-1 hover:bg-gray-100"><i class="fa-solid fa-arrow-down-wide-short"></i> <b>Decrescente</b></a>

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
        <a href="{{ $resetRoute ? route($resetRoute) : url()->current() }}" class="block px-3 py-1 text-red-600 hover:bg-gray-100"><b>Azzera filtri</b></a>
    </div>
</th>
