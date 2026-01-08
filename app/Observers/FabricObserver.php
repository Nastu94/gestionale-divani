<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Fabric;
use App\Services\ProductVariantsAutoAttachService;

/**
 * Observer per Fabric:
 * - Dopo la creazione di un nuovo tessuto, lo abbina automaticamente
 *   a tutti i prodotti attivi (whitelist product_fabrics).
 */
class FabricObserver
{
    /**
     * Evento "created": scatta dopo il salvataggio del nuovo Fabric.
     *
     * @param \App\Models\Fabric $fabric Tessuto appena creato.
     */
    public function created(Fabric $fabric): void
    {
        /**
         * Usiamo il service via container per:
         * - non accoppiare l'observer a implementazioni concrete
         * - mantenere testabilità e riuso
         */
        app(ProductVariantsAutoAttachService::class)
            ->attachFabricToAllActiveProducts($fabric);
    }
}
