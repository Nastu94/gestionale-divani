<?php

namespace App\Helpers;

final class LotHelper
{
    /**
     * Restituisce il prossimo codice lotto dato l'ultimo registrato.
     *
     * AA000 → AA001 ... AA999 → AB000 ... AZ999 → BA000.
     *
     * @param  string|null $lastLot   ultimo lotto presente in tabella, o null se nessuno
     * @return string                 nuovo lotto
     */
    public static function next(?string $lastLot): string
    {
        // Se non c'è alcun lotto, partiamo da AA000
        if (empty($lastLot) || !preg_match('/^[A-Z]{2}\d{3}$/', $lastLot)) {
            return 'AA000';
        }

        [$letters, $numbers] = [substr($lastLot, 0, 2), substr($lastLot, 2)];

        // Incrementa la parte numerica
        if ((int)$numbers < 999) {
            return $letters . str_pad(((int)$numbers + 1), 3, '0', STR_PAD_LEFT);
        }

        // Reset numeri a 000 e incrementa lettere (base-26 “AA”, “AB” … “AZ”)
        [$first, $second] = str_split($letters);

        if ($second !== 'Z') {
            $second = chr(ord($second) + 1);           // AK → AL …
        } else {
            $second = 'A';
            $first  = chr(ord($first) + 1);            // AZ → BA
            if ($first > 'Z') {                        // ZZ → ciclo, riparte da AA
                $first = 'A';
            }
        }

        return $first . $second . '000';
    }
}
