{{-- resources/views/components/grid-menu.blade.php --}}

@php
    $sections = config('menu.grid_menu');
@endphp

<div x-data="{ openSection: null }" x-cloak @click.outside="openSection = null" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 p-4">
    @foreach($sections as $index => $section)
        @canany(collect($section['items'])->pluck('permission')->all())
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <!-- Section header -->
                <button
                    @click.stop="openSection === {{ $index }} ? openSection = null : openSection = {{ $index }}"
                    class="w-full flex items-center px-4 py-3 bg-indigo-50 dark:bg-gray-700 hover:bg-indigo-100 dark:hover:bg-gray-600 focus:outline-none transition">
                    <i class="fas {{ $section['icon'] }} text-xl text-indigo-600 dark:text-indigo-400"></i>
                    <span class="ml-3 flex-1 text-left font-semibold text-gray-800 dark:text-gray-200">
                        {{ $section['section'] }}
                    </span>
                    <i class="fas" :class="openSection === {{ $index }} ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>

                <!-- Section items: only visible when this section is open -->
                <div x-show="openSection === {{ $index }}" x-transition class="px-4 py-2 bg-gray-50 dark:bg-gray-700 space-y-2">
                    @foreach($section['items'] as $item)
                        @can($item['permission'])
                            <a href="{{ route($item['route']) }}"
                               class="flex items-center px-3 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                                <i class="fas {{ $item['icon'] ?? '' }} text-lg text-gray-500 dark:text-gray-400"></i>
                                <span class="ml-2 text-gray-700 dark:text-gray-200">
                                    {{ $item['label'] }}
                                </span>
                            </a>
                        @endcan
                    @endforeach
                </div>
            </div>
        @endcanany
    @endforeach
</div>
