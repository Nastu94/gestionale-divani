{{-- resources/views/components/dashboard-tiles.blade.php --}}

@php
    // $tiles viene iniettato dal View Composer in AppServiceProvider
    // e contiene già badge_count calcolati per ciascuna voce.
    // In alternativa, se non lo passi così, puoi fare:
    // $tiles = View::getShared()['menu']['dashboard_tiles'] ?? [];
    $tiles = $tiles ?? [];
@endphp

<div class="flex flex-wrap gap-4">
    {{-- Se l'utente non ha nessuno dei permessi previsti, non renderizza nulla --}}
    @canany(collect($tiles)->pluck('permission')->all())
        @foreach($tiles as $tile)
            @can($tile['permission'])
                <a href="{{ route($tile['route']) }}"
                   class="flex items-center p-4 bg-white dark:bg-gray-800 rounded-lg shadow
                          hover:shadow-lg transition w-full sm:w-auto">
                    {{-- Icona --}}
                    <div class="flex-shrink-0 p-2 bg-indigo-100 dark:bg-indigo-900 rounded-full">
                        <i class="fas {{ $tile['icon'] }} text-xl
                                  text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    {{-- Label + Badge --}}
                    <div class="ml-3">
                        <span class="block text-sm font-medium
                                     text-gray-700 dark:text-gray-200">
                            {{ $tile['label'] }}
                        </span>

                        {{-- Badge count già numerico (0 se assente) --}}
                        @if(array_key_exists('badge_count', $tile) && is_numeric($tile['badge_count']))
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $tile['badge_count'] }}
                            </span>
                        @endif
                    </div>
                </a>
            @endcan
        @endforeach
    @endcanany
</div>
