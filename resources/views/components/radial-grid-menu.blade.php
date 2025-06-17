{{-- resources/views/components/radial-grid-menu.blade.php --}}
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

<div
    x-data="{
        openKey: null,
        openRow: null,
        columns: window.innerWidth >= 768 ? 3 : (window.innerWidth >= 640 ? 2 : 1),
        init() {
            window.addEventListener('resize', () => {
                this.columns = window.innerWidth >= 768 ? 3 : (window.innerWidth >= 640 ? 2 : 1);
            });
        },
        totalRows() {
            return Math.ceil({{ count($sections) }} / this.columns);
        },
        rowGap() {
            return this.openKey !== null ? '8rem' : '2.5rem';
        },
        padTop() {
            return (this.openKey !== null && this.openRow === 0) ? '6rem' : '0';
        },
        padBot() {
            return (this.openKey !== null && this.openRow === this.totalRows() - 1) ? '6rem' : '0';
        }
    }"
    x-init="init()"
    class="
      grid
      grid-cols-1        {{-- 1 colonna su smartphone --}}
      sm:grid-cols-2     {{-- 2 colonne su tablet --}}
      md:grid-cols-3     {{-- 3 colonne su desktop --}}
      gap-x-10           {{-- gap orizzontale --}}
      overflow-visible
      transition-all duration-100
    "
    :style="`row-gap:${rowGap()}; padding-top:${padTop()}; padding-bottom:${padBot()}`"
>
    @foreach($sections as $idx => $section)
        @canany(collect($section['items'])->pluck('permission')->all())
        <div class="relative flex justify-center" x-ref="module{{ $idx }}">

            {{-- Pulsante macro-modulo --}}
            <button
                :data-row="Math.floor({{ $idx }} / columns)"
                @click.stop="
                    openKey = (openKey === '{{ $idx }}') ? null : '{{ $idx }}';
                    openRow = openKey !== null ? Number($event.currentTarget.dataset.row) : null;
                    if (openKey === '{{ $idx }}') {
                        $nextTick(() => {
                            setTimeout(() => {
                                $refs['module{{ $idx }}'].scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'center'
                                });
                            }, 100);
                        });
                    }
                "
                x-ref="module{{ $idx }}"
                class="
                  w-28 h-28 bg-white dark:bg-gray-800 rounded-lg shadow
                  flex flex-col items-center justify-center
                  hover:bg-indigo-50 dark:hover:bg-indigo-900
                  transition
                "
            >
                <i class="fas {{ $section['icon'] }} text-3xl text-indigo-600 dark:text-indigo-400"></i>
                <span class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">
                    {{ $section['section'] }}
                </span>
            </button>

            {{-- Menu radiale dei sottomoduli --}}
            <template x-if="openKey === '{{ $idx }}'">
                <div
                    class="absolute inset-0 flex items-center justify-center pointer-events-none z-30"
                    x-cloak
                    @click.outside="openKey = null; openRow = null"
                >
                    @php
                        $items = collect($section['items'])
                                  ->filter(fn($i) => auth()->user()->can($i['permission']))
                                  ->values()
                                  ->take(8);
                    @endphp

                    @foreach($items as $k => $item)
                        @php $o = $offsets[$k]; @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            class="
                              absolute w-14 h-14 bg-white dark:bg-gray-800 rounded-full shadow-lg z-40
                              flex flex-col items-center justify-center hover:scale-110 transition
                              pointer-events-auto
                            "
                            style="
                              left:50%; top:50%;
                              transform:translate(-50%,-50%) translate({{ $o['x'] }}px,{{ $o['y'] }}px);
                            "
                        >
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
