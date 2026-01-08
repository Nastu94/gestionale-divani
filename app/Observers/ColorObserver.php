<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Color;
use App\Services\ProductVariantsAutoAttachService;

/**
 * Observer per Color:
 * - Dopo la creazione di un nuovo colore, lo abbina automaticamente
 *   a tutti i prodotti attivi (whitelist product_colors).
 */
class ColorObserver
{
    /**
     * Evento "created": scatta dopo il salvataggio del nuovo Color.
     *
     * @param \App\Models\Color $color Colore appena creato.
     */
    public function created(Color $color): void
    {
        app(ProductVariantsAutoAttachService::class)
            ->attachColorToAllActiveProducts($color);
    }
}
