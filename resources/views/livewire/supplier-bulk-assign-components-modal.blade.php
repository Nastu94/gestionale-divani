<div>
    @if($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            {{-- Overlay --}}
            <div class="absolute inset-0 bg-black opacity-75" wire:click="closeModal"></div>

            {{-- Box modale --}}
            <div class="relative z-10 w-full max-w-3xl bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden max-h-[90vh] flex flex-col min-h-0">
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Assegnazione massiva componenti
                        </h3>
                        <p class="text-xs text-gray-600 dark:text-gray-300">
                            Fornitore: <span class="font-semibold">{{ $supplierName ?: ('#'.$supplierId) }}</span>
                            • Selezionati: <span class="font-semibold">{{ count($selected) }}</span>
                        </p>
                    </div>

                    <button type="button" wire:click="closeModal" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                {{-- UI Message --}}
                @if($uiMessage)
                    <div
                        wire:key="uiMessage-{{ md5($uiMessageType.'|'.$uiMessage) }}"
                        x-data="{ show: true }"
                        x-init="setTimeout(() => show = false, 5000)"
                        x-show="show"
                        x-transition.opacity.duration.500ms
                        class="px-6 py-3"
                    >
                        <div class="rounded border px-4 py-2 text-sm
                            @if($uiMessageType==='success') bg-green-100 border-green-300 text-green-800
                            @elseif($uiMessageType==='error') bg-red-100 border-red-300 text-red-800
                            @else bg-blue-100 border-blue-300 text-blue-800
                            @endif
                        ">
                            {{ $uiMessage }}
                        </div>
                    </div>
                @endif

                {{-- Filtri --}}
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        {{-- Categoria --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Categoria</label>
                            <select wire:model.live="filters.category_id"
                                    class="mt-1 w-full border rounded px-2 py-1 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-100">
                                <option value="">Tutte</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Attivi --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Stato</label>
                            <select wire:model.live="filters.active"
                                    class="mt-1 w-full border rounded px-2 py-1 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-100">
                                <option value="1">Attivi</option>
                                <option value="0">Non attivi</option>
                                <option value="all">Tutti</option>
                            </select>
                        </div>

                        {{-- Solo non assegnati --}}
                        <div class="flex items-end">
                            <label class="inline-flex items-center text-xs text-gray-700 dark:text-gray-300">
                                <input type="checkbox" wire:model.live="filters.only_unassigned" class="mr-2">
                                Solo non assegnati
                            </label>
                        </div>

                        {{-- Ricerca --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Ricerca</label>
                            <input type="text"
                                   wire:model.live.debounce.300ms="filters.q"
                                   placeholder="Codice o descrizione…"
                                   class="mt-1 w-full border rounded px-2 py-1 text-sm bg-gray-50 dark:bg-gray-700 dark:text-gray-100">
                        </div>
                    </div>
                </div>

                {{-- LISTA (questa è la parte critica) --}}
                <div
                    class="px-6 py-4 flex-1 min-h-0 flex flex-col"
                    x-data="{ inFlight: false, hasMore: @entangle('hasMore') }"
                >
                    <div
                        class="flex-1 min-h-0 overflow-y-auto border rounded dark:border-gray-700"
                        x-ref="scrollRoot"
                        x-on:scroll.passive.throttle.200ms="
                            if (inFlight) return;
                            if (!hasMore) return;

                            const el = $refs.scrollRoot;
                            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 120) {
                                inFlight = true;
                                $wire.loadMore().then(() => { inFlight = false; });
                            }
                        "
                        x-on:bulk-assign-scroll-top.window="$refs.scrollRoot.scrollTop = 0"
                    >
                        <div class="p-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @forelse($components as $cmp)
                                @php
                                    $id = (int) $cmp->id;
                                    $isAssigned = array_key_exists($id, $assignedMap);
                                @endphp

                                <div
                                    wire:key="cmp-{{ $id }}"
                                    class="border rounded-md p-3
                                        {{ $isAssigned ? 'bg-emerald-50 border-emerald-200' : 'bg-gray-50 border-gray-200' }}
                                        dark:border-gray-700 dark:bg-gray-900/20"
                                >
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input
                                            wire:key="chk-{{ $id }}"
                                            type="checkbox"
                                            value="{{ $id }}"
                                            wire:model.live="selected"
                                            @disabled($isAssigned)
                                            class="mt-1"
                                        >

                                        <div class="min-w-0">
                                            <div class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                {{ $cmp->code }}
                                            </div>
                                            <div class="text-xs text-gray-600 dark:text-gray-300 line-clamp-2">
                                                {{ $cmp->description }}
                                            </div>

                                            @if($isAssigned)
                                                <div class="mt-2">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                                bg-emerald-200 text-emerald-900 dark:bg-emerald-800 dark:text-emerald-100">
                                                        Già assegnato
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </label>
                                </div>
                            @empty
                                <div class="col-span-full p-4 text-sm text-gray-500 dark:text-gray-300">
                                    Nessun componente trovato con i filtri correnti.
                                </div>
                            @endforelse
                        </div>

                        <div class="h-10 flex items-center justify-center">
                            <span wire:loading class="text-xs text-gray-500 dark:text-gray-300">Caricamento…</span>
                            @if(!$hasMore && count($components) > 0)
                                <span class="text-xs text-gray-500 dark:text-gray-300">Fine lista</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex-none bg-white dark:bg-gray-800 flex items-center justify-end gap-2">
                    <button type="button"
                            wire:click="closeModal"
                            class="px-4 py-1.5 text-xs font-medium rounded bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-100">
                        Annulla
                    </button>

                    <button
                        type="button"
                        wire:click="selectAllMatching"
                        class="px-4 py-1.5 text-xs font-medium rounded bg-sky-600 text-white hover:bg-sky-500"
                    >
                        Seleziona tutti i filtrati
                    </button>

                    <button
                        type="button"
                        wire:click="clearSelection"
                        class="px-4 py-1.5 text-xs font-medium rounded bg-gray-300 text-gray-800 hover:bg-gray-200"
                    >
                        Svuota selezione
                    </button>

                    <button
                        type="button"
                        wire:click="assignSelected"
                        wire:loading.attr="disabled"
                        @disabled(count($selected) === 0)
                        class="px-4 py-1.5 text-xs font-medium rounded text-white
                            {{ count($selected) === 0 ? 'bg-indigo-400 opacity-60 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-500' }}"
                    >
                        Assegna ({{ count($selected) }})
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
