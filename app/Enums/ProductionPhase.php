<?php
/**
 * Elenco ordinato delle 7 fasi di produzione.
 * Il valore int viene usato sia nel DB (TINYINT) che in cast/enum.
 */
namespace App\Enums;

enum ProductionPhase: int
{
    case INSERTED     = 0;
    case ASSEMBLY     = 1; // Cucito
    case FINISHING    = 2; // Assemblaggio
    case SHIPPING     = 3; // Spedizione

    /**
     * Etichetta in italiano per UI e report.
     */
    public function label(): string
    {
        return match ($this) {
            self::INSERTED   => 'Inserito',
            self::ASSEMBLY   => 'Cucito',
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
