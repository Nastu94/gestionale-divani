<?php

namespace App\Models\Concerns;

use App\Helpers\LotHelper;

trait GeneratesLot
{
    /**
     * Genera e assegna il prossimo lotto interno.
     *
     * @return void
     */
    public function generateLot(): void
    {
        $lastLot = static::query()->latest('internal_lot_code')->value('internal_lot_code');
        $this->internal_lot_code = LotHelper::next($lastLot);
    }
}
