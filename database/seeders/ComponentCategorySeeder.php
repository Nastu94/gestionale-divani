<?php

namespace Database\Seeders;

use App\Models\ComponentCategory;
use App\Enums\ProductionPhase;
use Illuminate\Database\Seeder;

class ComponentCategorySeeder extends Seeder
{
    /** @var array<array<string,string>> */
    protected array $categories = [
        // prefisso, nome, descrizione
        ['code' => 'GAMBE', 'name' => 'Gambe',        'description' => 'Piedini e gambe in legno o metallo'],
        ['code' => 'TESSU', 'name' => 'Tessuti',      'description' => 'Rivestimenti in tessuto'],
        ['code' => 'PELLI', 'name' => 'Pelli',        'description' => 'Rivestimenti in pelle/ecopelle'],
        ['code' => 'CUSCI', 'name' => 'Cuscini',      'description' => 'Cuscini di seduta e schienale'],
        ['code' => 'FODER', 'name' => 'Fodere',       'description' => 'Fodere interne o esterne'],
        ['code' => 'TELAI', 'name' => 'Telai',        'description' => 'Strutture portanti in legno o metallo'],
        ['code' => 'MECCN', 'name' => 'Meccanismi',   'description' => 'Relax, alzate, scorrimenti'],
        ['code' => 'MOLLE', 'name' => 'Molle',        'description' => 'Molle a spirale, zig-zag ecc.'],
        ['code' => 'SCHIU', 'name' => 'Schiume',      'description' => 'Poliuretano espanso, memory'],
        ['code' => 'IMBOT', 'name' => 'Imbottiture',  'description' => 'Blocchi finiti, ovatta, piuma'],
        ['code' => 'LEGNO', 'name' => 'Legno',        'description' => 'Elementi in legno non strutturali'],
        ['code' => 'METAL', 'name' => 'Metallo',      'description' => 'Componenti metallici vari'],
        ['code' => 'PLAST', 'name' => 'Plastica',     'description' => 'Componenti in plastica'],
        ['code' => 'GOMMA', 'name' => 'Gomma',        'description' => 'Guarnizioni, elementi in gomma'],
        ['code' => 'ELETT', 'name' => 'Elettronica',  'description' => 'Componenti elettronici e cablaggi'],
        ['code' => 'ILLUM', 'name' => 'Illuminazione','description' => 'LED e illuminazione integrata'],
        ['code' => 'MANIG', 'name' => 'Maniglie',     'description' => 'Maniglie, pomelli, leve'],
        ['code' => 'ISOLA', 'name' => 'Isolamento',   'description' => 'Materiali isolanti termo-acustici'],
        ['code' => 'RIVST', 'name' => 'Rivestimenti', 'description' => 'Rivestimenti tecnici e interni'],
        ['code' => 'FISSA', 'name' => 'Fissaggi',     'description' => 'Viti, bulloni, sistemi di fissaggio'],
        ['code' => 'VERNI', 'name' => 'Verniciatura', 'description' => 'Trattamenti e finiture superficiali'],
    ];

    /**
     * Popola la tabella con le categorie tipiche di un divano.
     */
    public function run(): void
    {
        /** @var int[] $phasePool  Fasi 1-5 (Structure→Finishing)  */
        $phasePool = range(
            ProductionPhase::STRUCTURE->value,
            ProductionPhase::FINISHING->value
        );

        foreach ($this->categories as $idx => $catData) {

            /** @var ComponentCategory $cat */
            $cat = ComponentCategory::firstOrCreate(
                ['code' => $catData['code']],
                $catData
            );

            /**
             * Assegna la fase in modo ciclico:
             * garantiamo che ogni fase 1-5 appaia almeno una volta,
             * poi si ricomincia (distribuzione “random-deterministica”).
             */
            $phaseValue = $phasePool[$idx % count($phasePool)];

            // Crea la riga pivot se non esiste
            $cat->phaseLinks()->firstOrCreate([
                'phase' => $phaseValue,
            ]);
        }
    }

}
