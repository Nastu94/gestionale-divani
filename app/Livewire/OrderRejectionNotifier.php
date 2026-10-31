<?php

namespace App\Livewire;

use App\Models\Alert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

class OrderRejectionNotifier extends Component
{
    public function mount(): void
    {
        $this->authorizeAlerts();
        $this->state();
    }

    public function refreshAlerts(): void
    {
        $this->authorizeAlerts();
    }

    public function dismiss(int $alertId): void
    {
        $this->authorizeAlerts();

        if (!$this->newAlerts()->whereKey($alertId)->exists()) {
            return;
        }

        $state = $this->state();
        $state['dismissed'] = array_values(array_unique([...$state['dismissed'], $alertId]));
        session()->put($this->sessionKey(), $state);
    }

    public function openAlert(int $alertId): void
    {
        $this->authorizeAlerts();
        abort_unless(auth()->user()->can('orders.customer.view'), 403);
        $this->newAlerts()->whereKey($alertId)->firstOrFail();

        $this->dismiss($alertId);
        $this->redirectRoute('alerts.show', ['alert' => $alertId]);
    }

    public function render(): View
    {
        $this->authorizeAlerts();

        return view('livewire.order-rejection-notifier', [
            'notifications' => $this->newAlerts()
                ->whereNotIn('id', $this->state()['dismissed'])
                ->orderBy('id')
                ->limit(5)
                ->get(['id', 'message']),
        ]);
    }

    private function authorizeAlerts(): void
    {
        /** Controlla il permesso anche nelle richieste periodiche e nelle azioni Livewire. */
        abort_unless(auth()->user()?->can('alerts.view'), 403);
    }

    private function newAlerts(): Builder
    {
        /** La lettura di un altro operatore non deve nascondere il nuovo avviso a questa sessione. */
        return Alert::query()
            ->where('type', 'order_rejected')
            ->where('id', '>', $this->state()['initial_id']);
    }

    private function state(): array
    {
        $key = $this->sessionKey();

        if (!session()->has($key)) {
            /** Alla prima apertura parte dai nuovi eventi; gli avvisi precedenti restano nell'elenco Alert. */
            session()->put($key, [
                'initial_id' => (int) Alert::query()->max('id'),
                'dismissed' => [],
            ]);
        }

        return session()->get($key);
    }

    private function sessionKey(): string
    {
        return 'order_rejection_notifier.'.auth()->id();
    }
}
