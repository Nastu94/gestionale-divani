{{-- resources/views/vendor/livewire/tailwind-compact.blade.php --}}
{{-- Pagination "compact" compatibile con Livewire (niente reload pagina) --}}

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between px-4 py-3">

        {{-- Pulsante PRECEDENTE --}}
        <div>
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-400 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">
                    ‹ Prec
                </span>
            @else
                <button type="button"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    ‹ Prec
                </button>
            @endif
        </div>

        {{-- Numeri pagina (compatti: current ± 1, con ellissi) --}}
        <div class="flex items-center gap-1">
            @php
                /**
                 * Range compatto:
                 * - Prima pagina
                 * - Ultima pagina
                 * - Pagine intorno alla corrente (± 1)
                 */
                $current = $paginator->currentPage();
                $last    = $paginator->lastPage();
                $window  = 1;

                $pages = collect([1, $last])
                    ->merge(range(max(1, $current - $window), min($last, $current + $window)))
                    ->unique()
                    ->sort()
                    ->values();

                $prevPage = null;
            @endphp

            @foreach ($pages as $page)
                @php
                    // Inserisce "…" quando c’è un salto > 1
                    $gap = $prevPage !== null ? ($page - $prevPage) : 0;
                @endphp

                @if ($prevPage !== null && $gap > 1)
                    <span class="px-2 text-xs text-gray-400 select-none">…</span>
                @endif

                @if ($page == $current)
                    <span aria-current="page"
                          class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-purple-600 border border-purple-600 rounded-md">
                        {{ $page }}
                    </span>
                @else
                    <button type="button"
                            wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        {{ $page }}
                    </button>
                @endif

                @php $prevPage = $page; @endphp
            @endforeach
        </div>

        {{-- Pulsante SUCCESSIVA --}}
        <div>
            @if ($paginator->hasMorePages())
                <button type="button"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Succ ›
                </button>
            @else
                <span class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-gray-400 bg-gray-100 border border-gray-200 rounded-md cursor-not-allowed">
                    Succ ›
                </span>
            @endif
        </div>

    </nav>
@endif