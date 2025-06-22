<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('components.dashboard-tiles', function($view) {
            $rawTiles = config('menu.dashboard_tiles', []);

            $tiles = collect($rawTiles)->map(function($tile) {
                // Calcola badge_count in base alla chiave
                $tile['badge_count'] = match ($tile['badge_key'] ?? null) {
                    'customers'       => \App\Models\Customer::count(),
                    'orders_customer' => \App\Models\Order::where('cause','!=','purchase')->count(),
                    'alerts_critical' => \App\Models\Alert::where('triggered_at','<=', now())->count(),
                    'alerts_low'      => \App\Models\Alert::where('type','low_stock')->count(),
                    default           => 0,
                };
                return $tile;
            })->toArray();

            // PASSA esattamente la variabile che il tuo componente si aspetta
            $view->with('tiles', $tiles);
        });
    }
}
