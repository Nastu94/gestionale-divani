<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class SuppliersFromExcelSeeder extends Seeder
{
    /**
     * Esegue l'import massivo dei fornitori da XLSX nella tabella `suppliers`.
     *
     * - Aggiorna i record esistenti se trova la stessa `vat_number` (che è unique in DB).
     * - Se un record è soft-deleted, lo ripristina (impostando `deleted_at` a null).
     * - Per righe senza P.IVA prova un match “debole” (name + città) per ridurre duplicati.
     *
     * Documentazione Seeder Laravel: ogni seeder espone `run()` ed è eseguibile via `db:seed`. 
     */
    public function run(): void
    {
        /** @var string $filePath Path assoluto al file XLSX (versionato nel progetto). */
        $filePath = base_path('database/seeders/data/Fornitori.xlsx');

        // Verifica presenza file per evitare seed “silenziosi” che non fanno nulla.
        if (! file_exists($filePath)) {
            throw new RuntimeException(
                "File fornitori non trovato: {$filePath}. Copialo in database/seeders/data/Fornitori.xlsx"
            );
        }

        // Carica il foglio Excel (usiamo formatData=true per preservare zeri iniziali, es. P.IVA che inizia con 0).
        $spreadsheet = IOFactory::load($filePath);

        // Se esiste un foglio chiamato “Fornitori”, usiamo quello; altrimenti prendiamo l’attivo.
        $sheet = $spreadsheet->getSheetByName('Fornitori') ?? $spreadsheet->getActiveSheet();

        /**
         * Trasformiamo in array con:
         * - $formatData = true -> valori formattati (fondamentale per codici/P.IVA con zeri iniziali)
         * - $returnCellRef = true -> chiavi A,B,C... più comode da mappare
         */
        $rows = $sheet->toArray(null, true, true, true);

        // Rimuove header (prima riga).
        array_shift($rows);

        $now = Carbon::now();

        // Contatori utili per output a console.
        $inserted = 0;
        $updated  = 0;
        $skipped  = 0;

        DB::transaction(function () use ($rows, $now, &$inserted, &$updated, &$skipped): void {
            foreach ($rows as $index => $row) {
                /**
                 * Mappatura colonne Excel:
                 * A: flag (es. "No") -> lo interpretiamo come "non bloccato" => attivo
                 * B: Cod. (non usato nello schema attuale)
                 * C: Denominazione -> suppliers.name
                 * D: Città -> address.city
                 * E: Prov. -> address.province (extra, utile)
                 * F: Partita Iva -> suppliers.vat_number (unique)
                 */
                $rawFlag = $this->cleanString($row['A'] ?? null);
                $rawCode = $this->cleanString($row['B'] ?? null); // Attualmente NON salvabile nello schema
                $name    = $this->cleanString($row['C'] ?? null);
                $city    = $this->cleanString($row['D'] ?? null);
                $prov    = $this->cleanString($row['E'] ?? null);
                $vat     = $this->normalizeVat($row['F'] ?? null);

                // Se manca la denominazione, la riga non è inseribile (name è NOT NULL).
                if ($name === null || $name === '') {
                    $skipped++;
                    continue;
                }

                // Interpretiamo il flag: se un domani comparisse “Sì”, lo consideriamo disattivo.
                $isActive = $this->flagMeansActive($rawFlag);

                // Costruiamo il JSON address coerente con la migration (street/city/zip/country) + province.
                $country = $this->inferCountryFromVat($vat, $prov);
                $address = [
                    'street'   => null,
                    'city'     => $city,
                    'zip'      => null,
                    'country'  => $country,
                    'province' => $prov, // chiave extra (non rompe nulla: è un JSON)
                ];

                /** @var array<string, mixed> $payload Dati da scrivere in suppliers. */
                $payload = [
                    'name'          => $name,
                    'vat_number'    => $vat,
                    'tax_code'      => null,
                    'phone'         => null,
                    'email'         => null,
                    'address'       => json_encode($address, JSON_UNESCAPED_UNICODE),
                    'website'       => null,
                    'payment_terms' => null,
                    'is_active'     => $isActive,
                    'updated_at'    => $now,
                    'deleted_at'    => null, // “ripristina” se era archiviato (soft delete)
                ];

                // Strategia:
                // 1) Se c’è P.IVA: match certo su vat_number (unique)
                // 2) Se manca: match “debole” su name + città (se disponibile)
                $existingId = null;

                if ($vat !== null) {
                    $existingId = DB::table('suppliers')
                        ->where('vat_number', '=', $vat)
                        ->value('id');
                } else {
                    $query = DB::table('suppliers')->whereNull('vat_number')->where('name', '=', $name);

                    // Se abbiamo la città, proviamo a matchare anche su quella dentro al JSON (MySQL JSON_EXTRACT).
                    if ($city !== null) {
                        $query->where('address->city', '=', $city);
                    }

                    $existingId = $query->value('id');
                }

                if ($existingId !== null) {
                    // Update record esistente.
                    DB::table('suppliers')->where('id', '=', $existingId)->update($payload);
                    $updated++;
                } else {
                    // Insert nuovo record (created_at obbligatorio).
                    $payload['created_at'] = $now;
                    DB::table('suppliers')->insert($payload);
                    $inserted++;
                }

                // Nota: $rawCode è presente nel file ma non salvato (manca colonna in DB).
                // Se decidi di aggiungere `suppliers.code`, qui è già pronto da mappare.
                unset($rawCode);
            }
        });

        // Output leggibile quando lanci il seeder da Artisan.
        $this->command?->info("Suppliers import completato: inseriti={$inserted}, aggiornati={$updated}, saltati={$skipped}");
    }

    /**
     * Pulisce stringhe: trim e normalizza vuoti a null.
     */
    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);

        return $str === '' ? null : $str;
    }

    /**
     * Normalizza la P.IVA:
     * - rimuove spazi
     * - mantiene lettere+numeri (utile per VAT estere tipo "PL...")
     * - converte a uppercase
     */
    private function normalizeVat(mixed $value): ?string
    {
        $vat = $this->cleanString($value);

        if ($vat === null) {
            return null;
        }

        // Rimuove spazi e caratteri non alfanumerici.
        $vat = preg_replace('/[^a-zA-Z0-9]/', '', $vat);
        $vat = strtoupper((string) $vat);

        return $vat === '' ? null : $vat;
    }

    /**
     * Interpreta il flag della colonna A.
     * Assunzione ragionevole:
     * - "No" / vuoto => attivo
     * - "Si/Sì/Yes/1/true" => NON attivo
     */
    private function flagMeansActive(?string $flag): bool
    {
        if ($flag === null) {
            return true;
        }

        $normalized = strtolower($flag);

        $meansBlocked = in_array($normalized, ['si', 'sì', 'yes', 'y', '1', 'true'], true);

        return ! $meansBlocked;
    }

    /**
     * Deduce la nazione:
     * - se la VAT inizia con 2 lettere: usa quelle (es. "PL...")
     * - altrimenti, se c’è una provincia italiana (2 lettere): IT
     * - fallback: IT
     */
    private function inferCountryFromVat(?string $vat, ?string $prov): string
    {
        if ($vat !== null && preg_match('/^[A-Z]{2}/', $vat) === 1) {
            return substr($vat, 0, 2);
        }

        if ($prov !== null && preg_match('/^[A-Z]{2}$/', strtoupper($prov)) === 1) {
            return 'IT';
        }

        return 'IT';
    }
}
