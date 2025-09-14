<x-guest-layout>
    <div class="max-w-2xl mx-auto p-6">
        @if($expired)
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                <p class="font-semibold">@lang('orders.confirm.link_expired')</p>
                <p class="text-sm">@lang('orders.confirm.link_help', ['days' => $ttl_days])</p>
            </div>
        @else
            <h1 class="text-xl font-bold mb-4">@lang('orders.confirm.title')</h1>

            <p>@lang('orders.confirm.subtitle', ['order' => $order->orderNumber->full ?? ('#'.$order->id)])</p>

            <ul class="list-disc pl-5 mt-3">
                @foreach($order->items as $it)
                    <li>{{ $it->quantity }} × {{ $it->product->name }} — {{ number_format($it->unit_price, 2, ',', '.') }} €</li>
                @endforeach
            </ul>

            <p class="mt-3 font-semibold">
                @lang('orders.confirm.total'): {{ number_format($order->total, 2, ',', '.') }} €
            </p>
            <p class="text-sm text-gray-500">
                @lang('orders.confirm.delivery_on'): {{ \Carbon\Carbon::parse($order->delivery_date)->isoFormat('LL') }}
            </p>

            <div class="flex gap-3 mt-6">
                {{-- Conferma --}}
                <form method="POST" action="{{ route('orders.customer.confirm.accept', $token) }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded">
                        @lang('orders.confirm.btn_confirm')
                    </button>
                </form>

                {{-- Rifiuta --}}
                <form method="POST" action="{{ route('orders.customer.confirm.reject', $token) }}"
                      class="flex items-center gap-2">
                    @csrf
                    <input type="text" name="reason" required class="border rounded px-2 py-1"
                           placeholder="@lang('orders.confirm.reason_ph')" minlength="3" maxlength="1000">
                    <button type="submit" class="px-4 py-2 bg-rose-600 text-white rounded">
                        @lang('orders.confirm.btn_reject')
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-guest-layout>
