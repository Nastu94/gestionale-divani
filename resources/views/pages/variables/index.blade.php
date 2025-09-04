{{-- resources/views/pages/variables/index.blade.php --}}
{{-- ----------------------------------------------------------------------------
Pagina: "Variabili (Fabrics & Colors) → Mapping TESSU"
- Filtri (state, active, fabric, color, ricerca)
- Badge statistiche (totali, mappati, non mappati, conflitti)
- Tabella componenti TESSU (lettura, evidenzia conflitti e mapping mancante)
- Matrice fabric×color → SKU (verde/rosso) a lato (STRICT)
---------------------------------------------------------------------------- --}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg text-gray-800 dark:text-gray-200">
                Variabili (Fabrics & Colors) — Mapping TESSU
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('variables.index') }}" class="text-sm underline">Ricarica</a>
            </div>
        </div>

        @if($tessuMissing ?? false)
            <div class="mt-3 bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-2 rounded">
                La categoria componenti <strong>TESSU</strong> non è presente. Crea la categoria per abilitare il mapping.
            </div>
        @endif
    </x-slot>

    <div class="py-4 grid grid-cols-12 gap-4">
        {{-- Colonna principale (tabella) --}}
        <div class="col-span-12 lg:col-span-8 space-y-4">
            {{-- Filtri --}}
            <form method="GET" action="{{ route('variables.index') }}" class="bg-white shadow rounded p-4">
                <div class="grid grid-cols-12 gap-3 items-end">
                    <div class="col-span-12 md:col-span-2">
                        <label class="block text-xs text-gray-600">Stato mapping</label>
                        <select name="state" class="border rounded w-full px-2 py-1">
                            <option value="all"      @selected($filters['state']==='all')>Tutti</option>
                            <option value="mapped"   @selected($filters['state']==='mapped')>Mappati</option>
                            <option value="unmapped" @selected($filters['state']==='unmapped')>Non mappati</option>
                            <option value="conflicts"@selected($filters['state']==='conflicts')>Conflitti</option>
                        </select>
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-xs text-gray-600">Attivo</label>
                        <select name="active" class="border rounded w-full px-2 py-1">
                            <option value="all" @selected($filters['active']==='all')>Tutti</option>
                            <option value="1"   @selected($filters['active']==='1')>Attivi</option>
                            <option value="0"   @selected($filters['active']==='0')>Disattivi</option>
                        </select>
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-xs text-gray-600">Tessuto</label>
                        <select name="fabric_id" class="border rounded w-full px-2 py-1">
                            <option value="">— Tutti —</option>
                            @foreach($fabrics as $f)
                                <option value="{{ $f->id }}" @selected($filters['fabric_id']===$f->id)>{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-6 md:col-span-3">
                        <label class="block text-xs text-gray-600">Colore</label>
                        <select name="color_id" class="border rounded w-full px-2 py-1">
                            <option value="">— Tutti —</option>
                            @foreach($colors as $c)
                                <option value="{{ $c->id }}" @selected($filters['color_id']===$c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-6 md:col-span-2">
                        <label class="block text-xs text-gray-600">Ricerca</label>
                        <input type="text" name="q" value="{{ $filters['q'] }}" class="border rounded w-full px-2 py-1" placeholder="codice/descrizione">
                    </div>
                    <div class="col-span-12 md:col-span-12 flex gap-2">
                        <button class="px-3 py-1 rounded bg-blue-600 text-white" type="submit">Filtra</button>
                        <a class="px-3 py-1 rounded bg-gray-100" href="{{ route('variables.index') }}">Pulisci</a>
                    </div>
                </div>
            </form>

            {{-- Statistiche --}}
            <div class="bg-white shadow rounded p-3 flex flex-wrap gap-3 text-sm">
                <span class="px-3 py-1 rounded bg-gray-100">Totali: <strong>{{ $stats['total'] }}</strong></span>
                <span class="px-3 py-1 rounded bg-green-100 text-green-800">Mappati: <strong>{{ $stats['mapped'] }}</strong></span>
                <span class="px-3 py-1 rounded bg-yellow-100 text-yellow-800">Non mappati: <strong>{{ $stats['unmapped'] }}</strong></span>
                <span class="px-3 py-1 rounded bg-red-100 text-red-800">Conflitti: <strong>{{ $stats['conflicts'] }}</strong></span>
            </div>

            {{-- Tabella componenti TESSU --}}
            <div class="bg-white shadow rounded">
                <div class="px-4 py-3 border-b font-semibold">Componenti TESSU</div>
                <div class="p-0 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left bg-gray-50">
                                <th class="px-3 py-2">Codice</th>
                                <th class="px-3 py-2">Descrizione</th>
                                <th class="px-3 py-2">Tessuto</th>
                                <th class="px-3 py-2">Colore</th>
                                <th class="px-3 py-2">Stato SKU</th>
                                <th class="px-3 py-2">Attivo</th>
                                @can('product-variables.manage')
                                    <th class="px-3 py-2 text-right">Azioni</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($components as $cmp)
                                @php
                                    $mapped = $cmp->fabric_id && $cmp->color_id;
                                    $dup    = $isDuplicate($cmp->fabric_id, $cmp->color_id);
                                    $fabricName = $cmp->fabric_id ? (optional($fabrics->firstWhere('id', $cmp->fabric_id))->name ?? null) : null;
                                    $colorName  = $cmp->color_id  ? (optional($colors->firstWhere('id', $cmp->color_id))->name ?? null) : null;
                                @endphp
                                <tr class="border-t @if(!$mapped) bg-yellow-50 @elseif($dup) bg-red-50 @endif">
                                    <td class="px-1 py-2 font-mono">{{ $cmp->code }}</td>
                                    <td class="px-1 py-2">{{ $cmp->description }}</td>
                                    <td class="px-1 py-2">
                                        @if($cmp->fabric_id)
                                            {{ $fabricName ?? '—' }}
                                        @else
                                            <span class="text-yellow-700">— non impostato —</span>
                                        @endif
                                    </td>
                                    <td class="px-1 py-2">
                                        @if($cmp->color_id)
                                            {{ $colorName ?? '—' }}
                                        @else
                                            <span class="text-yellow-700">— non impostato —</span>
                                        @endif
                                    </td>
                                    <td class="px-1 py-2">
                                        @if(!$mapped)
                                            <span class="inline-block px-2 py-0.5 rounded bg-yellow-200 text-yellow-900">Non mappato</span>
                                        @elseif($dup)
                                            <span class="inline-block px-2 py-0.5 rounded bg-red-200 text-red-900">Conflitto</span>
                                        @else
                                            <span class="inline-block px-2 py-0.5 rounded bg-green-200 text-green-900">OK</span>
                                        @endif
                                    </td>
                                    <td class="px-1 py-2">{{ $cmp->is_active ? 'Sì' : 'No' }}</td>
                                    @can('product-variables.manage')
                                        <td class="px-1 py-2 text-right">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-2 px-3 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-700"
                                                title="Abbina Tessuto × Colore a questo componente"
                                                {{-- Dispatch evento Alpine: apre il modale e precompila i valori correnti --}}
                                                @click="$dispatch('open-matching-modal', {
                                                    componentId: {{ $cmp->id }},
                                                    code:        @js($cmp->code),
                                                    description: @js($cmp->description),
                                                    fabricId:    {{ $cmp->fabric_id ? (int)$cmp->fabric_id : 'null' }},
                                                    fabricName:  @js($fabricName),
                                                    colorId:     {{ $cmp->color_id ? (int)$cmp->color_id : 'null' }},
                                                    colorName:   @js($colorName),
                                                })"
                                            >
                                                {{-- icona a tema “abbina”/“link” --}}
                                                <i class="fa-solid fa-link"></i>
                                            </button>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr class="border-t">
                                    <td colspan="6" class="px-3 py-6 text-center text-gray-500">
                                        Nessun componente TESSU trovato con i filtri correnti.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 border-t">
                    {{ $components->links() }}
                </div>
            </div>
        </div>

        {{-- Colonna laterale (matrice) --}}
        <div class="col-span-12 lg:col-span-4 space-y-4">
            <div class="bg-white shadow rounded p-3">
                <div class="font-semibold mb-2">Matrice SKU (Fabric × Color)</div>
                <div class="overflow-auto">
                    <table class="text-xs border">
                        <thead>
                            <tr>
                                <th class="p-1 bg-gray-50 border">Tessuto \ Colore</th>
                                @foreach($colors as $color)
                                    <th class="p-1 bg-gray-50 border whitespace-nowrap">
                                        @if($color->hex)
                                            <span class="inline-block w-3 h-3 rounded border" style="background: {{ $color->hex }}"></span>
                                        @endif
                                        {{ $color->name }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fabrics as $fabric)
                                <tr>
                                    <td class="p-1 border font-medium whitespace-nowrap">{{ $fabric->name }}</td>
                                    @foreach($colors as $color)
                                        @php $cell = $matrix[$fabric->id][$color->id] ?? null; @endphp
                                        <td class="p-1 border text-center">
                                            @if($cell)
                                                <span class="inline-block px-2 py-0.5 text-green-700 bg-green-100 rounded"
                                                      title="SKU {{ $cell['code'] }} — {{ $cell['is_active'] ? 'attivo' : 'disattivo' }}">
                                                    {{ $cell['code'] }}
                                                </span>
                                            @else
                                                <span class="inline-block px-2 py-0.5 text-red-700 bg-red-100 rounded" title="Nessun componente per questa coppia">
                                                    N/D
                                                </span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pulsanti operativi (verranno abilitati nelle prossime fasi) --}}
                @can('product-variables.create')
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <button class="px-3 py-1 rounded bg-gray-200 cursor-not-allowed" title="In arrivo">+ Nuovo Tessuto</button>
                        <button class="px-3 py-1 rounded bg-gray-200 cursor-not-allowed" title="In arrivo">+ Nuovo Colore</button>
                        <button class="px-3 py-1 rounded bg-gray-200 cursor-not-allowed" title="In arrivo">Crea componenti mancanti</button>
                    </div>
                @endcan
            </div>

            {{-- Suggerimenti/Regole --}}
            <div class="bg-white shadow rounded p-3 text-xs text-gray-700">
                <div class="font-semibold mb-1">Regole (STRICT)</div>
                <ul class="list-disc pl-4 space-y-1">
                    <li>Ogni coppia tessuto×colore selezionabile deve avere uno SKU in TESSU.</li>
                    <li>I conflitti indicano coppie duplicate su più componenti: risolvere prima di usarle nei prodotti.</li>
                    <li>Fabrics & Colors “globali” definiscono le maggiorazioni di fallback; gli override per-prodotto si configurano dalla modale “Variabili”.</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Inclusione del MODALE "Abbina tessuto×colore" --}}
    {{-- Passiamo a props i cataloghi e la matrice per validazioni client-side --}}
    <x-product-variable-matching-modal :fabrics="$fabrics" :colors="$colors" :matrix="$matrix" />
</x-app-layout>
