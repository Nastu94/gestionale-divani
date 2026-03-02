<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Eccezione di dominio: l'avanzamento non può proseguire perché
 * mancano prenotazioni componenti per la fase di destinazione.
 *
 * Serve a trasportare verso la UI (Livewire) un payload strutturato
 * con le quantità mancanti, per consentire l'azione "Forza Prenotazione".
 */
class ForceReservationRequiredException extends RuntimeException
{
    /**
     * Dettagli mancanti per componente.
     *
     * Ogni elemento:
     * - component_id (int)
     * - code (string)
     * - needed (float)
     * - reserved (float)
     * - missing (float)
     *
     * @var array<int, array<string, int|float|string>>
     */
    private array $missingComponents;

    /**
     * @param array<int, array<string, int|float|string>> $missingComponents
     * @param string $message Messaggio human-readable (UI/log)
     */
    public function __construct(array $missingComponents, string $message)
    {
        parent::__construct($message);

        $this->missingComponents = $missingComponents;
    }

    /**
     * Ritorna i dettagli strutturati dei mancanti.
     *
     * @return array<int, array<string, int|float|string>>
     */
    public function missingComponents(): array
    {
        return $this->missingComponents;
    }
}