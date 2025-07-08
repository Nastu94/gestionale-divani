<?php

namespace App\Observers;

use App\Models\StockLevelLot;

class StockLevelLotObserver
{
    /** ricalcola la quantità totale dopo create / update / delete */
    public function saved(StockLevelLot $lot): void
    {
        $this->syncQuantity($lot);
    }

    public function deleted(StockLevelLot $lot): void
    {
        $this->syncQuantity($lot);
    }

    private function syncQuantity(StockLevelLot $lot): void
    {
        $stock = $lot->stockLevel;
        $stock->quantity = $stock->lots()->sum('quantity');
        $stock->saveQuietly();           // evitiamo loop di eventi
    }
}
