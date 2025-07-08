<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\StockLevelLot;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Alert;
use App\Observers\StockLevelLotObserver;

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
        // Registrazione degli observer per i modelli        
        StockLevelLot::observe(StockLevelLotObserver::class);
        
        // Componente dashboard-tiles: passaggio dei dati delle tiles
        // Questo composer si occupa di calcolare i badge_count in base alla chiave specificata
        // in ciascuna tile della configurazione.
        View::composer('components.dashboard-tiles', function($view) {
            $rawTiles = config('menu.dashboard_tiles', []);

            $tiles = collect($rawTiles)->map(function($tile) {
                // Calcola badge_count in base alla chiave
                switch ($tile['badge_key'] ?? null) {
                    case 'customers':
                        $tile['badge_count'] = Customer::count();
                        break;
                    case 'orders_customer':
                        $tile['badge_count'] = Order::where('cause','!=','purchase')->count();
                        break;
                    case 'alerts_critical':
                        $tile['badge_count'] = Alert::where('triggered_at','<=', now())->count();
                        break;
                    case 'alerts_low':
                        $tile['badge_count'] = Alert::where('type','low_stock')->count();
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
