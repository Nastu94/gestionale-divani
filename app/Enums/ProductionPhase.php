<?php
/**
 * Elenco ordinato delle 7 fasi di produzione.
 * Il valore int viene usato sia nel DB (TINYINT) che in cast/enum.
 */
namespace App\Enums;

enum ProductionPhase: int
{
    case INSERTED     = 0;
    case STRUCTURE    = 1;
    case PADDING      = 2;
    case UPHOLSTERY   = 3;
    case ASSEMBLY     = 4;
    case FINISHING    = 5;
    case SHIPPING     = 6;

    /**
     * Restituisce la fase successiva o null se siamo all'ultima.
     */
    public function next(): ?self
    {
        return self::tryFrom($this->value + 1);
    }
}
