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
                switch ($tile['badge_key'] ?? null) {
                    case 'customers':
                        $tile['badge_count'] = \App\Models\Customer::count();
                        break;
                    case 'orders_customer':
                        $tile['badge_count'] = \App\Models\Order::where('cause','!=','purchase')->count();
                        break;
                    case 'alerts_critical':
                        $tile['badge_count'] = \App\Models\Alert::where('triggered_at','<=', now())->count();
                        break;
                    case 'alerts_low':
                        $tile['badge_count'] = \App\Models\Alert::where('type','low_stock')->count();
                        break;
                    default:
                        $tile['badge_count'] = 0;
                        break;
                }
                return $tile;
            })->toArray();

            // PASSA esattamente la variabile che il tuo componente si aspetta
            $view->with('tiles', $tiles);
        });
    }
}
