<?php
/**
 * Seeder: crea alcuni tessuti di base (catalogo attributi).
 * Policy iniziale: maggiorazioni globali a 0 per semplicità.
 * PHP 8.4 / Laravel 12
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Fabric;

class FabricSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotente: crea se non esiste, aggiorna se cambia qualcosa
        $rows = [
            ['name' => 'Cotone',  'code' => 'COT', 'surcharge_type' => 'fixed',   'surcharge_value' => 0, 'active' => true],
            ['name' => 'Lino',    'code' => 'LIN', 'surcharge_type' => 'percent', 'surcharge_value' => 0, 'active' => true],
            ['name' => 'Velluto', 'code' => 'VEL', 'surcharge_type' => 'percent', 'surcharge_value' => 0, 'active' => true],
            // Aggiungi altri tessuti quando servirà (Pelle, Microfibra, ecc.)
        ];

        foreach ($rows as $r) {
            Fabric::updateOrCreate(
                ['name' => $r['name']],  // Chiave naturale semplice
                $r
            );
        }

        $this->command?->info('✅ Tessuti (fabrics) seed: ok.');
    }
}
