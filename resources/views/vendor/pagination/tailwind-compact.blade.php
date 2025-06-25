{{-- resources/views/vendor/pagination/tailwind-compact.blade.php --}}
{{-- Questo file è una versione compatta della paginazione Tailwind --}}
{{-- Puoi personalizzarlo ulteriormente secondo le tue esigenze --}}

@if ($paginator->hasPages())
    <nav role="navigation" class="flex justify-center" aria-label="Paginazione">
        {{-- Precedente --}}
        @if ($paginator->onFirstPage())
            <span class="px-3 py-1 text-gray-400 select-none">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
               class="px-3 py-1 text-indigo-600 hover:underline">‹</a>
        @endif

        {{-- Pagina precedente (solo se esiste) --}}
        @if ($paginator->currentPage() > 1)
            <a href="{{ $paginator->url($paginator->currentPage() - 1) }}"
               class="px-3 py-1 text-gray-600 hover:underline">
                {{ $paginator->currentPage() - 1 }}
            </a>
        @endif

        {{-- Pagina corrente --}}
        <span class="px-3 py-1 font-semibold text-white bg-indigo-600 rounded">
            {{ $paginator->currentPage() }}
        </span>

        {{-- Pagina successiva (solo se esiste) --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->url($paginator->currentPage() + 1) }}"
               class="px-3 py-1 text-gray-600 hover:underline">
                {{ $paginator->currentPage() + 1 }}
            </a>
        @endif

        {{-- Successivo --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
               class="px-3 py-1 text-indigo-600 hover:underline">›</a>
        @else
            <span class="px-3 py-1 text-gray-400 select-none">›</span>
        @endif
    </nav>
@endif
