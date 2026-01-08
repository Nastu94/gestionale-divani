<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Color;
use App\Models\Fabric;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service per auto-abbinare nuove variabili (tessuti/colori)
 * a tutti i prodotti attivi.
 *
 * NOTE:
 * - Operazione idempotente: usa insertOrIgnore sulle pivot (unique per coppia).
 * - Non tocca surcharge/is_default esistenti.
 * - Abbina SOLO a prodotti attivi e non soft-deleted.
 */
class ProductVariantsAutoAttachService
{
    /**
     * Abbina il tessuto appena creato a tutti i prodotti attivi.
     *
     * @param  \App\Models\Fabric  $fabric  Tessuto appena creato.
     * @return int Numero di righe inserite nella pivot product_fabrics.
     */
    public function attachFabricToAllActiveProducts(Fabric $fabric): int
    {
        // Se il tessuto non è attivo, non lo aggiungiamo alle whitelist dei prodotti.
        if (! (bool) $fabric->active) {
            return 0;
        }

        /** @var \Illuminate\Support\Carbon $now Timestamp unico usato per created_at/updated_at */
        $now = Carbon::now();

        // Recupera tutti i prodotti attivi (e non archiviati/soft-deleted)
        $productIds = Product::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        // Se non ci sono prodotti, non facciamo nulla.
        if ($productIds->isEmpty()) {
            return 0;
        }

        // Prepara righe pivot in batch (con 139 prodotti è leggero, ma resta scalabile).
        $rows = [];
        foreach ($productIds as $pid) {
            $rows[] = [
                'product_id'      => (int) $pid,
                'fabric_id'       => (int) $fabric->id,
                'surcharge_type'  => null,
                'surcharge_value' => null,
                'is_default'      => 0,     // tu hai detto: lasciamo false/0
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        /**
         * insertOrIgnore:
         * - Se la coppia (product_id, fabric_id) esiste già, viene ignorata (grazie alla unique).
         * - Ritorna il numero di righe effettivamente inserite (dipende dal driver DB, su MySQL ok).
         */
        return DB::table('product_fabrics')->insertOrIgnore($rows);
    }

    /**
     * Abbina il colore appena creato a tutti i prodotti attivi.
     *
     * @param  \App\Models\Color  $color  Colore appena creato.
     * @return int Numero di righe inserite nella pivot product_colors.
     */
    public function attachColorToAllActiveProducts(Color $color): int
    {
        // Se il colore non è attivo, non lo aggiungiamo alle whitelist dei prodotti.
        if (! (bool) $color->active) {
            return 0;
        }

        /** @var \Illuminate\Support\Carbon $now Timestamp unico usato per created_at/updated_at */
        $now = Carbon::now();

        // Recupera tutti i prodotti attivi (e non archiviati/soft-deleted)
        $productIds = Product::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        // Se non ci sono prodotti, non facciamo nulla.
        if ($productIds->isEmpty()) {
            return 0;
        }

        // Prepara righe pivot in batch.
        $rows = [];
        foreach ($productIds as $pid) {
            $rows[] = [
                'product_id'      => (int) $pid,
                'color_id'        => (int) $color->id,
                'surcharge_type'  => null,
                'surcharge_value' => null,
                'is_default'      => 0,     // tu hai detto: lasciamo false/0
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        return DB::table('product_colors')->insertOrIgnore($rows);
    }
}
