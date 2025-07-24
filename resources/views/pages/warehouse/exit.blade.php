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
        {{-- Flash messages standard --}}
        @foreach (['success' => 'green', 'error' => 'red'] as $k => $c)
            @if (session($k))
                <div  x-data="{ show:true }" x-init="setTimeout(()=>show=false,8000)" x-show="show" x-transition.opacity
                      class="bg-{{ $c }}-100 border border-{{ $c }}-400 text-{{ $c }}-700 px-4 py-3 rounded relative mt-2">
                    <i class="fas {{ $k=='success' ? 'fa-check-circle':'fa-exclamation-triangle' }} mr-2"></i>
                    <span>{{ session($k) }}</span>
                </div>
            @endif
        @endforeach
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
