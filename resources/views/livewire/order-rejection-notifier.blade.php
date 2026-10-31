<div wire:poll.10s="refreshAlerts"
     aria-live="polite"
     aria-relevant="additions"
     style="position: fixed; right: 1rem; bottom: 1rem; width: min(28rem, calc(100vw - 2rem)); max-height: 60vh; overflow-y: auto; z-index: 60;">
    @foreach($notifications as $notification)
        <section wire:key="order-rejection-{{ $notification->id }}"
                 class="mb-3 rounded-lg border border-red-200 bg-white p-4 text-gray-900 shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                 aria-label="Ordine rifiutato">
            <h2 class="font-semibold">Ordine rifiutato</h2>
            <p class="mt-2 text-sm whitespace-pre-line" style="overflow-wrap: anywhere;">{{ $notification->message }}</p>
            <div class="mt-3 flex flex-wrap gap-3">
                @can('orders.customer.view')
                    <a href="{{ route('alerts.show', $notification) }}"
                       wire:click.prevent="openAlert({{ $notification->id }})"
                       class="text-sm underline">Apri ordine</a>
                @else
                    <a href="{{ route('alerts.index') }}" class="text-sm underline">Apri Alert</a>
                @endcan
                <button type="button"
                        wire:click="dismiss({{ $notification->id }})"
                        class="text-sm underline"
                        aria-label="Chiudi questo avviso di ordine rifiutato">Chiudi</button>
            </div>
        </section>
    @endforeach
</div>
