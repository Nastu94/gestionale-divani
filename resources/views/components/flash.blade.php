@foreach (['success'=>'green','error'=>'red'] as $k=>$c)
    @if (session()->has($k))
        <div  x-data="{ show:true }"
              x-init="setTimeout(()=>show=false,10000)"
              x-show="show"
              x-transition.opacity
              class="flex justify-between bg-{{ $c }}-100 border border-{{ $c }}-400
                     text-{{ $c }}-700 px-4 py-3 rounded relative mb-2">
            <div>
                <i class="fas {{ $k=='success' ? 'fa-check-circle' : 'fa-exclamation-triangle' }} mr-2"></i>
                {!! nl2br(e(session($k))) !!}
            </div>
            {{-- Pulsante visibile solo quando l'ultima richiesta di avanzamento ha fallito per mancanza prenotazioni --}}
            @if($canForceReservation && !empty($forceMissingComponents))
                <button
                    type="button"
                    class="inline-flex items-center px-3 py-1.5
                            bg-red-900 hover:bg-red-800
                            text-xs font-semibold text-white rounded-md"
                    wire:click="openForceReservation"
                >
                    Forza Prenotazione
                </button>
            @endif
        </div>
    @endif
@endforeach
