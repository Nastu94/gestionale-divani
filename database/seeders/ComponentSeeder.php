<?php
/**
 * Popola la tabella components con 100 record realistici.
 *
 * Ogni categoria ha un generatore di descrizioni ad hoc
 * (materiale, dimensione, variante …) così da ottenere testi pertinenti.
 */

namespace Database\Seeders;

use Illuminate\Support\Facades\Schema;
use App\Models\Component;
use App\Models\ComponentCategory;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ComponentSeeder extends Seeder
{
    public function run(): void
    {
        
        // 1) Disabilita i vincoli FK per evitare errori
        Schema::disableForeignKeyConstraints();

        // 2) Svuota la tabella (hard delete + reset AUTO_INCREMENT)
        Component::truncate();

        // 3) Riabilita i vincoli
        Schema::enableForeignKeyConstraints();

        $faker      = Faker::create('it_IT');
        $categories = ComponentCategory::all();

        if ($categories->isEmpty()) {
            $this->command->error('⚠️  Nessuna categoria trovata. Avvia prima ComponentCategorySeeder.');
            return;
        }

        /* --------------------------------------------------------------
         |  Generatori di descrizioni: mappa prefisso → closure(Faker)
         |  Se manca la chiave, viene usato un fallback generico.
         * --------------------------------------------------------------*/
        $descriptionGen = [
            'GAMBE' => fn() => 'Gamba ' . $faker->randomElement(['in legno', 'in metallo']) .
                               ' h' . $faker->numberBetween(8,20) . ' cm',
            'TESSU' => fn() => 'Tessuto ' . $faker->colorName() . ' ' .
                               $faker->randomElement(['cotone', 'lino', 'microfibra']),
            'PELLI' => fn() => 'Pelle ' . $faker->randomElement(['fiore', 'corretto', 'ecopelle']),
            'CUSCI' => fn() => 'Cuscino ' . $faker->randomElement(['seduta', 'schienale']) .
                               ' ' . $faker->numberBetween(40,60) . '×' . $faker->numberBetween(40,60),
            'FODER' => fn() => 'Fodera ' . $faker->randomElement(['sfoderabile', 'impermeabile']),
            'TELAI' => fn() => 'Telaio ' . $faker->randomElement(['betulla', 'faggio', 'acciaio']),
            'MECCN' => fn() => 'Meccanismo relax ' . $faker->randomElement(['manuale', 'elettrico']),
            'MOLLE' => fn() => 'Molle ' . $faker->randomElement(['zig-zag', 'bonnell']),
            'SCHIU' => fn() => 'Schiuma densità ' . $faker->numberBetween(25,35) . ' kg/m³',
            'IMBOT' => fn() => 'Imbottitura in ' . $faker->randomElement(['ovatta', 'poliuretano', 'piuma']),
            'LEGNO' => fn() => 'Listello legno ' . $faker->randomElement(['abete', 'faggio']),
            'METAL' => fn() => 'Piastra metallo sp.' . $faker->randomFloat(1,1,5) . ' mm',
            'PLAST' => fn() => 'Elemento plastica ABS ' . strtoupper($faker->bothify('??-###')),
            'GOMMA' => fn() => 'Tampone gomma Ø' . $faker->numberBetween(10,30) . ' mm',
            'ELETT' => fn() => 'Centralina ' . $faker->randomElement(['USB', 'Bluetooth', 'Touch']),
            'ILLUM' => fn() => 'Striscia LED ' . $faker->numberBetween(30,120) . ' cm',
            'MANIG' => fn() => 'Maniglia finitura ' . $faker->randomElement(['cromata', 'nichel']),
            'ISOLA' => fn() => 'Pannello isolante ' . $faker->randomElement(['fono', 'termo']),
            'RIVST' => fn() => 'Rete rivestimento ' . $faker->randomElement(['elastica', '3D']),
            'FISSA' => fn() => 'Kit fissaggio ' . $faker->randomElement(['viti M8', 'bulloni M10']),
            'VERNI' => fn() => 'Vernice ' . $faker->safeColorName() . ' opaca',
        ];

        /* Progressivo per prefisso */
        $counters = [];
        foreach ($categories as $cat) {
            $lastCode = Component::withTrashed()
                ->where('category_id', $cat->id)
                ->where('code', 'like', "{$cat->code}-%")
                ->orderBy('code', 'desc')
                ->value('code');

            $counters[$cat->id] = $lastCode
                ? ((int) substr($lastCode, strlen($cat->code) + 1) + 1)
                : 1;
        }

        /* Inserimento di 100 record */
        for ($i = 0; $i < 100; $i++) {
            $cat      = $categories->random();
            $number   = str_pad($counters[$cat->id], 5, '0', STR_PAD_LEFT);
            $code     = "{$cat->code}-{$number}";
            $counters[$cat->id]++;

            // usa generatore specifico o fallback
            $description = $descriptionGen[$cat->code]()
                            ?? ucfirst($faker->words(3, true));

            Component::create([
                'category_id'     => $cat->id,
                'code'            => $code,
                'description'     => $description,
                'material'        => $faker->randomElement(['legno', 'metallo', 'plastica', 'gomma', 'tessuto', null]),
                'length'          => $faker->randomFloat(1, 5, 200),
                'width'           => $faker->randomFloat(1, 5, 200),
                'height'          => $faker->randomFloat(1, 2, 60),
                'weight'          => $faker->randomFloat(2, 0.1, 25),
                'unit_of_measure' => $faker->randomElement(['pz', 'm', 'kg', 'ml']),
                'is_active'       => $faker->boolean(90),
            ]);
        }

        $this->command->info('✅  100 componenti realistici creati con successo.');
    }
}
