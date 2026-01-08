<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AttachAllFabricsAndColorsToProductsSeeder extends Seeder
{
    /**
     * DRY-RUN:
     * - Calcola quante combinazioni prodotto×tessuto e prodotto×colore dovrebbero esistere
     * - Calcola quante esistono già nelle pivot
     * - Stampa quante mancano (quindi quante inseriremo nello step successivo)
     *
     * NOTA:
     * - Di default consideriamo solo record "attivi" e non soft-deleted.
     *   Products: is_active=1 AND deleted_at IS NULL
     *   Fabrics/Colors: active=1 AND deleted_at IS NULL
     */
    public function run(): void
    {
        /** @var bool $onlyActive Se true, limita ai record attivi e non archiviati */
        $onlyActive = true;

        // -----------------------------
        // Conteggi base (set "target")
        // -----------------------------
        $productsCount = DB::table('products')
            ->when($onlyActive, fn ($q) => $q->where('is_active', true))
            ->whereNull('deleted_at')
            ->count();

        $fabricsCount = DB::table('fabrics')
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->whereNull('deleted_at')
            ->count();

        $colorsCount = DB::table('colors')
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->whereNull('deleted_at')
            ->count();

        // Combinazioni teoriche: prodotto×tessuti e prodotto×colori
        $desiredProductFabrics = $productsCount * $fabricsCount;
        $desiredProductColors  = $productsCount * $colorsCount;

        // ----------------------------------------------------
        // Conteggi esistenti nelle pivot (solo set "target")
        // ----------------------------------------------------
        $existingProductFabrics = DB::table('product_fabrics as pf')
            ->join('products as p', 'p.id', '=', 'pf.product_id')
            ->join('fabrics as f', 'f.id', '=', 'pf.fabric_id')
            ->when($onlyActive, function ($q) {
                // Prodotti attivi + non soft-deleted
                $q->where('p.is_active', true)->whereNull('p.deleted_at');

                // Tessuti attivi + non soft-deleted
                $q->where('f.active', true)->whereNull('f.deleted_at');
            })
            ->count();

        $existingProductColors = DB::table('product_colors as pc')
            ->join('products as p', 'p.id', '=', 'pc.product_id')
            ->join('colors as c', 'c.id', '=', 'pc.color_id')
            ->when($onlyActive, function ($q) {
                // Prodotti attivi + non soft-deleted
                $q->where('p.is_active', true)->whereNull('p.deleted_at');

                // Colori attivi + non soft-deleted
                $q->where('c.active', true)->whereNull('c.deleted_at');
            })
            ->count();

        // Mancanti (non dovrebbero mai andare sotto 0, ma proteggiamo)
        $missingProductFabrics = max(0, $desiredProductFabrics - $existingProductFabrics);
        $missingProductColors  = max(0, $desiredProductColors  - $existingProductColors);

        // -----------------------------
        // Output console (DRY-RUN)
        // -----------------------------
        $this->command?->info('=== DRY-RUN Varianti Prodotti (tutti-su-tutti) ===');
        $this->command?->line('Filtro attivi: ' . ($onlyActive ? 'SI (consigliato)' : 'NO (include inattivi/archiviati)'));

        $this->command?->line("Prodotti target: {$productsCount}");
        $this->command?->line("Tessuti  target: {$fabricsCount}");
        $this->command?->line("Colori   target: {$colorsCount}");

        $this->command?->line("product_fabrics -> desiderati: {$desiredProductFabrics} | esistenti: {$existingProductFabrics} | mancanti: {$missingProductFabrics}");
        $this->command?->line("product_colors  -> desiderati: {$desiredProductColors}  | esistenti: {$existingProductColors}  | mancanti: {$missingProductColors}");

        // Warning se stiamo per inserire “tante” righe (indicativo)
        $totalMissing = $missingProductFabrics + $missingProductColors;
        if ($totalMissing > 200000) {
            $this->command?->warn("ATTENZIONE: righe mancanti totali ~ {$totalMissing}. Nel prossimo step useremo insert set-based per performance.");
        }

        $this->command?->info('DRY-RUN completato: nessuna scrittura su DB.');

        /**
         * Safety catch:
         * - Scriviamo sul DB SOLO se l'ambiente espone PRODUCT_VARIANTS_WRITE=1
         * - Così evitiamo inserimenti “accidentali”.
         */
        if ((int) env('PRODUCT_VARIANTS_WRITE', 0) !== 1) {
            $this->command?->warn(
                'Scrittura DISATTIVATA. Per inserire le coppie mancanti esegui: ' .
                'set PRODUCT_VARIANTS_WRITE=1 && php artisan db:seed --class=AttachAllFabricsAndColorsToProductsSeeder'
            );
            return;
        }

        /** @var \Illuminate\Support\Carbon $now Timestamp unico usato per created_at/updated_at */
        $now = Carbon::now();

        /**
         * Inserimento set-based (SQL) per performance e idempotenza.
         * - CROSS JOIN prodotti×tessuti / prodotti×colori
         * - LEFT JOIN sulla pivot per inserire SOLO dove manca la coppia
         * - Filtri “attivi + non soft-deleted” coerenti con la scelta fatta
         *
         * Pivot:
         * - product_fabrics: (product_id, fabric_id) unique + timestamps + is_default default false :contentReference[oaicite:1]{index=1}
         * - product_colors:  (product_id, color_id)  unique + timestamps + is_default default false :contentReference[oaicite:2]{index=2}
         */
        DB::transaction(function () use ($now): void {
            // 1) product_fabrics: inserisce tutte le coppie mancanti (surcharge null, is_default=0)
            $insertedFabrics = DB::affectingStatement(
                <<<SQL
                INSERT INTO `product_fabrics`
                    (`product_id`, `fabric_id`, `surcharge_type`, `surcharge_value`, `is_default`, `created_at`, `updated_at`)
                SELECT
                    p.`id`,
                    f.`id`,
                    NULL,
                    NULL,
                    0,
                    ?,
                    ?
                FROM `products` p
                CROSS JOIN `fabrics` f
                LEFT JOIN `product_fabrics` pf
                    ON pf.`product_id` = p.`id` AND pf.`fabric_id` = f.`id`
                WHERE
                    p.`is_active` = 1
                    AND p.`deleted_at` IS NULL
                    AND f.`active` = 1
                    AND f.`deleted_at` IS NULL
                    AND pf.`id` IS NULL
                SQL,
                [$now, $now]
            );

            // 2) product_colors: inserisce tutte le coppie mancanti (surcharge null, is_default=0)
            $insertedColors = DB::affectingStatement(
                <<<SQL
                INSERT INTO `product_colors`
                    (`product_id`, `color_id`, `surcharge_type`, `surcharge_value`, `is_default`, `created_at`, `updated_at`)
                SELECT
                    p.`id`,
                    c.`id`,
                    NULL,
                    NULL,
                    0,
                    ?,
                    ?
                FROM `products` p
                CROSS JOIN `colors` c
                LEFT JOIN `product_colors` pc
                    ON pc.`product_id` = p.`id` AND pc.`color_id` = c.`id`
                WHERE
                    p.`is_active` = 1
                    AND p.`deleted_at` IS NULL
                    AND c.`active` = 1
                    AND c.`deleted_at` IS NULL
                    AND pc.`id` IS NULL
                SQL,
                [$now, $now]
            );

            $this->command?->info("IMPORT completato: product_fabrics inseriti={$insertedFabrics}, product_colors inseriti={$insertedColors}");
        });
    }
}
