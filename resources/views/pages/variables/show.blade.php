{{-- resources/views/pages/variables/show.blade.php --}}
{{-- Vista "Dettaglio componente TESSU" --}}
{{-- Nota importante: dentro <x-app-layout> Blade usa la variabile $component per il layout.
     Per evitare conflitti, aliasiamo il nostro model Component in $tessu. --}}
@php
    /** @var \App\Models\Component $component */
    /** @var \App\Models\Fabric|null $fabric */
    /** @var \App\Models\Color|null $color */
    /** @var array $coherence */
    /** @var \Illuminate\Support\Collection $duplicates */
    /** @var \Illuminate\Support\Collection $usages */
    /** @var \Illuminate\Support\Collection $fabrics */
    /** @var \Illuminate\Support\Collection $colors */
    /** @var array $matrix */
    /** @var array $fabricAliases */
    /** @var array $colorAliases */
    /** @var array $ambiguousColorTerms */

    // 🔐 Evita conflitto con $component del layout Blade
    $tessu = $component;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200">
                    Variabili — Dettaglio componente {{ $tessu->code }}
                </h2>
                <div class="text-sm text-gray-500">
                    <a href="{{ route('variables.index') }}" class="underline">Torna all’elenco</a>
                </div>
            </div>

            {{-- Azione “Abbina” disponibile se permesso --}}
            @can('product-variables.manage')
                <button type="button"
                        class="px-3 py-1.5 rounded bg-indigo-600 text-white hover:bg-indigo-700"
                        title="Abbina Tessuto × Colore a questo componente"
                        @click="$dispatch('open-matching-modal', {
                            componentId: {{ $tessu->id }},
                            code:        @js($tessu->code),
                            description: @js($tessu->description),
                            fabricId:    {{ $tessu->fabric_id ? (int)$tessu->fabric_id : 'null' }},
                            fabricName:  @js($fabric?->name),
                            colorId:     {{ $tessu->color_id ? (int)$tessu->color_id : 'null' }},
                            colorName:   @js($color?->name),
                        })">
                    <i class="fa-solid fa-link mr-1"></i> Abbina
                </button>
            @endcan
        </div>
    </x-slot>

    <div class="py-4 grid grid-cols-12 gap-4">
        {{-- Colonna sinistra: riepilogo --}}
        <div class="col-span-12 lg:col-span-6 space-y-4">
            <div class="bg-white shadow rounded p-4">
                <div class="font-semibold mb-2">Riepilogo componente</div>
                <dl class="text-sm grid grid-cols-12 gap-y-2">
                    <dt class="col-span-4 text-gray-500">Codice</dt>
                    <dd class="col-span-8 font-mono">{{ $tessu->code }}</dd>

                    <dt class="col-span-4 text-gray-500">Descrizione</dt>
                    <dd class="col-span-8">{{ $tessu->description }}</dd>

                    <dt class="col-span-4 text-gray-500">Unità di misura</dt>
                    <dd class="col-span-8">{{ $tessu->unit_of_measure ?? '—' }}</dd>

                    <dt class="col-span-4 text-gray-500">Attivo</dt>
                    <dd class="col-span-8">
                        @if($tessu->is_active)
                            <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 text-xs">Sì</span>
                        @else
                            <span class="px-2 py-0.5 rounded bg-red-100 text-red-800 text-xs">No</span>
                        @endif
                    </dd>
                </dl>
            </div>

            <div class="bg-white shadow rounded p-4">
                <div class="font-semibold mb-2">Mapping Tessuto × Colore</div>
                <div class="text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500">Tessuto:</span>
                        @if($fabric)
                            <span class="px-2 py-0.5 rounded bg-gray-100">{{ $fabric->name }}</span>
                        @else
                            <span class="text-yellow-700">— non impostato —</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-gray-500">Colore:</span>
                        @if($color)
                            @if(!empty($color->hex))
                                <span class="inline-block w-3 h-3 rounded border" style="background: {{ $color->hex }}"></span>
                            @endif
                            <span class="px-2 py-0.5 rounded bg-gray-100">{{ $color->name }}</span>
                        @else
                            <span class="text-yellow-700">— non impostato —</span>
                        @endif
                    </div>

                    {{-- Pill Coerenza --}}
                    @php
                        $coh = $coherence ?? ['status'=>'ok','tooltip'=>''];
                        $cohClass = match($coh['status']) {
                            'warning' => 'bg-orange-200 text-orange-900',
                            'info'    => 'bg-blue-200 text-blue-900',
                            default   => 'bg-green-200 text-green-900',
                        };
                        $cohLabel = strtoupper($coh['status']);
                    @endphp
                    <div class="mt-3">
                        <span class="inline-block px-2 py-0.5 rounded {{ $cohClass }}" title="{{ $coh['tooltip'] ?? '' }}">
                            Coerenza: {{ $cohLabel }}
                        </span>
                        @if(!empty($coh['tooltip']))
                            <div class="text-xs text-gray-600 mt-1">{{ $coh['tooltip'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Colonna destra: duplicati e impieghi --}}
        <div class="col-span-12 lg:col-span-6 space-y-4">
            <div class="bg-white shadow rounded p-4">
                <div class="font-semibold mb-2">Duplicati (stessa coppia tessuto×colore)</div>
                @if($tessu->fabric_id && $tessu->color_id)
                    @if($duplicates->isEmpty())
                        <div class="text-sm text-gray-600">Nessun duplicato trovato. ✅</div>
                    @else
                        <div class="text-sm text-red-800 mb-2">
                            Attenzione: esistono altri {{ $duplicates->count() }} SKU con la stessa coppia.
                        </div>
                        <div class="overflow-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="bg-red-50 text-red-900 text-left">
                                        <th class="px-2 py-1">Codice</th>
                                        <th class="px-2 py-1">Descrizione</th>
                                        <th class="px-2 py-1">Attivo</th>
                                        <th class="px-2 py-1 text-right">Apri</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($duplicates as $dup)
                                        <tr class="border-t">
                                            <td class="px-2 py-1 font-mono">{{ $dup->code }}</td>
                                            <td class="px-2 py-1">{{ $dup->description }}</td>
                                            <td class="px-2 py-1">{{ $dup->is_active ? 'Sì' : 'No' }}</td>
                                            <td class="px-2 py-1 text-right">
                                                <a href="{{ route('variables.show', $dup->id) }}"
                                                   class="px-2 py-0.5 rounded bg-white border hover:bg-gray-50 text-xs">
                                                    Dettaglio
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @else
                    <div class="text-sm text-gray-600">Mapping incompleto: impossibile calcolare duplicati.</div>
                @endif
            </div>

            <div class="bg-white shadow rounded p-4">
                <div class="font-semibold mb-2">Impiego nei prodotti</div>
                @if($usages->isEmpty())
                    <div class="text-sm text-gray-600">Questo componente non risulta impiegato nelle distinte base.</div>
                @else
                    <div class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left">
                                    <th class="px-2 py-1">Codice prodotto</th>
                                    <th class="px-2 py-1">Nome/Descrizione</th>
                                    <th class="px-2 py-1 text-right">Q.tà</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($usages as $u)
                                    <tr class="border-t">
                                        <td class="px-2 py-1 font-mono">{{ $u->product_code }}</td>
                                        <td class="px-2 py-1">{{ $u->product_name }}</td>
                                        <td class="px-2 py-1 text-right">
                                            {{ rtrim(rtrim(number_format((float)($u->qty ?? 1), 2, ',', '.'), '0'), ',') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(!$tessu->is_active)
                        <div class="mt-2 text-xs text-orange-800 bg-orange-50 border border-orange-200 rounded px-2 py-1">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            Questo componente è <strong>disattivo</strong> ma compare in una o più distinte base.
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Includo la modale "Abbina tessuto×colore" per operare direttamente dallo show --}}
    <x-product-variable-matching-modal
        :fabrics="$fabrics" :colors="$colors" :matrix="$matrix"
        :fabric-aliases="$fabricAliases"
        :color-aliases="$colorAliases"
        :ambiguous-color-terms="$ambiguousColorTerms"
    />
</x-app-layout>
