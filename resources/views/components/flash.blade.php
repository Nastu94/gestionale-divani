@foreach (['success'=>'green','error'=>'red'] as $k=>$c)
    @if (session()->has($k))
        <div  x-data="{ show:true }"
              x-init="setTimeout(()=>show=false,8000)"
              x-show="show"
              x-transition.opacity
              class="bg-{{ $c }}-100 border border-{{ $c }}-400
                     text-{{ $c }}-700 px-4 py-3 rounded relative mb-2">
            <i class="fas {{ $k=='success' ? 'fa-check-circle' : 'fa-exclamation-triangle' }} mr-2"></i>
            {!! nl2br(e(session($k))) !!}
        </div>
    @endif
@endforeach
