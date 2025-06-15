{{-- resources/views/components/dashboard-tiles.blade.php --}}

@php
    $tiles = config('menu.dashboard_tiles');
@endphp

<div class="flex flex-wrap gap-4">
    @canany(collect($tiles)->pluck('permission')->all())
        @foreach($tiles as $tile)
            @can($tile['permission'])
                <a href="{{ route($tile['route']) }}"
                   class="flex items-center p-4 bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition w-full sm:w-auto">
                    <!-- Icon wrapper -->
                    <div class="flex-shrink-0 p-2 bg-indigo-100 dark:bg-indigo-900 rounded-full">
                        <i class="fas {{ $tile['icon'] }} text-xl text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <!-- Label and count -->
                    <div class="ml-3">
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ $tile['label'] }}
                        </span>
                        @if(isset($tile['badge_count']))
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ is_callable($tile['badge_count'])
                                    ? $tile['badge_count']()
                                    : $tile['badge_count']
                                }}
                            </span>
                        @endif
                    </div>
                </a>
            @endcan
        @endforeach
    @endcanany
</div>

