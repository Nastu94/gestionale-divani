{{-- DASHBOARD ▸ Giacenze di magazzino --}}
<x-app-layout>

    {{-- HEADER --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Giacenze di magazzino') }}
            </h2>
            <x-dashboard-tiles />
        </div>
    </x-slot>

    {{-- COMPONENTE LIVEWIRE --}}
    <livewire:warehouse.stock-levels-table />

    {{-- Fallback SEO / no-JS  --}}
    <noscript>
        @include('components.stock-levels-static-table')
    </noscript>

</x-app-layout>
