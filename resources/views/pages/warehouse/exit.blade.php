{{-- resources/views/pages/warehouse/exit.blade.php --}}

{{--
    DASHBOARD ▸ Evasione ordini cliente (uscite magazzino)
    -------------------------------------------------------------------------
    Requisiti aggiuntivi rispetto alla prima bozza:
    • Toolbar azioni PER‑RIGA su seconda riga espandibile (stesso pattern “warehouses”)
    • KPI cards e filtri/ordinamento SENZA reload – tutto Livewire/Alpine.
    -------------------------------------------------------------------------
--}}

<x-app-layout>
    {{-- ╔══════════════ HEADER ══════════════╗ --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Evasione ordini cliente') }}
            </h2>
            <x-dashboard-tiles />
        </div>
    </x-slot>

    <livewire:warehouse.exit-table />

    {{-- ╔══════════ ALPINE DATA ══════════╗ --}}
    @push('scripts')
        <script>
            function exitCrud() {
                return { openId: null };
            }
        </script>
    @endpush
</x-app-layout>
