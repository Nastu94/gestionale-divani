<?php
/**
 * Seeder: crea alcuni colori base (catalogo attributi).
 * Policy iniziale: maggiorazioni globali a 0 per semplicità.
 * PHP 8.4 / Laravel 12
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Color;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Nero',   'code' => 'NER', 'hex' => '#000000', 'surcharge_type' => 'fixed',   'surcharge_value' => 0, 'active' => true],
            ['name' => 'Grigio', 'code' => 'GRI', 'hex' => '#808080', 'surcharge_type' => 'fixed',   'surcharge_value' => 0, 'active' => true],
            ['name' => 'Panna',  'code' => 'PAN', 'hex' => '#F8F0E3', 'surcharge_type' => 'percent', 'surcharge_value' => 0, 'active' => true],
            // Aggiungi altri colori quando servirà (Rosso, Blu, ecc.)
        ];

        foreach ($rows as $r) {
            Color::updateOrCreate(
                ['name' => $r['name']],
                $r
            );
        }

        $this->command?->info('✅ Colori (colors) seed: ok.');
    }
}
