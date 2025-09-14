<x-guest-layout>
    <div class="max-w-md mx-auto p-6">
        @if($ok ?? false)
            <div class="bg-emerald-50 border-l-4 border-emerald-600 p-4">
                <p class="font-semibold">{{ $message }}</p>
            </div>
        @else
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                <p class="font-semibold">{{ $message }}</p>
                <p class="text-sm">@lang('orders.confirm.link_help', ['days' => $ttl_days])</p>
            </div>
        @endif
    </div>
</x-guest-layout>
