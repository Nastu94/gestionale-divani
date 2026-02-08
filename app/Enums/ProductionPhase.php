<?php
/**
 * Elenco ordinato delle 7 fasi di produzione.
 * Il valore int viene usato sia nel DB (TINYINT) che in cast/enum.
 */
namespace App\Enums;

enum ProductionPhase: int
{
    case INSERTED     = 0;
    case UPHOLSTERY   = 1;
    case ASSEMBLY     = 2;
    case STRUCTURE    = 3;
    case PADDING      = 4;
    case FINISHING    = 5;
    case SHIPPING     = 6;

    /**
     * Etichetta in italiano per UI e report.
     */
    public function label(): string
    {
        return match ($this) {
            self::INSERTED   => 'Inserito',
            self::UPHOLSTERY => 'Taglio',
            self::ASSEMBLY   => 'Cucito',
            self::STRUCTURE  => 'Fusto',
            self::PADDING    => 'Spugna',
            self::FINISHING  => 'Assemblaggio',
            self::SHIPPING   => 'Spedizione',
        };
    }
    
    /**
     * Restituisce la fase successiva o null se siamo all'ultima.
     */
    public function next(): ?self
    {
        return self::tryFrom($this->value + 1);
    }
}
