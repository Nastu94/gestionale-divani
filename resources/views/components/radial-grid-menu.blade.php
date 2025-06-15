{{-- resources/views/components/radial-grid-menu-debug.blade.php --}}
@php
    $sections = config('menu.grid_menu');
    $offsets  = [
        ['x'=>  0,'y'=>-90],   // ↑
        ['x'=> 90,'y'=>  0],   // →
        ['x'=>  0,'y'=> 90],   // ↓
        ['x'=>-90,'y'=>  0],   // ←
        ['x'=> 60,'y'=>-60],   // ↗
        ['x'=> 60,'y'=> 60],   // ↘
        ['x'=>-60,'y'=> 60],   // ↙
        ['x'=>-60,'y'=>-60],   // ↖
    ];
@endphp

@once
    @push('scripts')
        <script>
            /**
            * Scrolla la pagina finché il centro di `el` non è circa al centro viewport.
            * extraY ti permette di spostarlo un po' più su o più giù.
            */
            function smoothScrollToBottom(extra = 0) {
                const target = document.body.scrollHeight + extra;
                window.scrollTo({ top: target, behavior: 'smooth' });
            }
        </script>
    @endpush
@endonce

<div
    x-data="{
        openKey: null,
        openRow: null,
        rowGap() { return this.openKey ? '8rem' : '2.5rem' },
        padTop() { return (this.openKey && this.openRow===0) ? '6rem' : '0' },
        padBot() { return (this.openKey && this.openRow===1) ? '6rem' : '0' },   /* ↑ più spazio */

        scrollAfterOpen() {
            if (this.openRow === 1) {          // solo seconda riga
                /* 1° tick: Alpine applica padding / gap  */
                this.$nextTick(() => {
                    /* 2° tick: attendiamo repaint + transizione (50-60 ms) */
                    setTimeout(() => smoothScrollToBottom(), 200);
                });
            }
        },
    }"
    class="grid grid-cols-3 gap-x-10 overflow-visible transition-all duration-300"
    :style="`row-gap:${rowGap()}; padding-top:${padTop()}; padding-bottom:${padBot()}`"
>
    @foreach($sections as $idx => $section)
        @canany(collect($section['items'])->pluck('permission')->all())
        <div class="relative flex justify-center">

            {{-- pulsante macro-modulo + label --}}
            <button
                :data-row="{{ intdiv($idx,3) }}"
                @click.stop="
                    openKey = (openKey==='{{ $idx }}') ? null : '{{ $idx }}';
                    openRow = openKey ? Number($event.currentTarget.dataset.row) : null;
                    scrollAfterOpen();
                "
                class="w-28 h-28 bg-white dark:bg-gray-800 rounded-lg shadow
                    flex flex-col items-center justify-center
                    hover:bg-indigo-50 dark:hover:bg-indigo-900 transition">
                <i class="fas {{ $section['icon'] }} text-3xl text-indigo-600 dark:text-indigo-400"></i>
                <span class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $section['section'] }}
                </span>
            </button>

            {{-- menu radiale --}}
            <template x-if="openKey==='{{ $idx }}'">
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-30"
                    x-cloak @click.outside="openKey=null; openRow=null">
                    @php
                        $items = collect($section['items'])
                                ->filter(fn($i)=>auth()->user()->can($i['permission']))
                                ->values()->take(4);
                    @endphp
                    @foreach($items as $k=>$item)
                        @php $o=$offsets[$k]; @endphp
                        <a href="{{ route($item['route']) }}"
                        class="absolute w-14 h-14 bg-white dark:bg-gray-800 rounded-full shadow-lg z-40
                                flex flex-col items-center justify-center hover:scale-110 transition
                                pointer-events-auto"
                        style="left:50%; top:50%;
                                transform:translate(-50%,-50%) translate({{ $o['x'] }}px,{{ $o['y'] }}px);">
                            <i class="fas {{ $item['icon'] }} text-lg text-gray-700 dark:text-gray-200"></i>
                            <span class="text-xs text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $item['label'] }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </template>

        </div>
        @endcanany
    @endforeach
</div>