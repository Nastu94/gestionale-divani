<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * DTO di ritorno da InventoryService::check().
 *
 * @property-read bool        $ok        True se tutta la BOM è coperta.
 * @property-read Collection  $shortage  Collezione di array:
 *                                       component_id, needed, available,
 *                                       incoming, shortage
 */
class AvailabilityResult
{
    public bool $ok;
    public Collection $shortage;

    public function __construct(bool $ok, Collection $shortage)
    {
        $this->ok = $ok;
        $this->shortage = $shortage;
    }
}
