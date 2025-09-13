<?php
declare(strict_types=1);

namespace App\Support\Pricing;

use App\Models\Product;
use App\Models\CustomerProductPrice; // Assicurati che punti alla tabella 'customer_product_prices'
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Risolutore del prezzo effettivo per (prodotto, cliente, data).
 *
 * Regole:
 * - Se esiste una versione cliente valida alla data → usa quella.
 * - Altrimenti → fallback al prezzo base del prodotto.
 * - Ritorna sempre il prezzo come stringa decimale (compatibile con DECIMAL DB).
 *
 * Logging:
 * - Log di livello debug in ingresso/uscita e ai punti decisionali.
 *
 * Caching:
 * - Memo per-request ($memo) per evitare query duplicate nello stesso hit.
 * - Micro-cache (60s) solo per risultati NON null; i null vengono ignorati.
 */
final class CustomerPriceResolver
{
    /** @var array<string, array|null> memo per-request: "product:customer:date" => result|null */
    private array $memo = [];

    /** ID breve per correlare i log in un singolo ciclo di vita della classe */
    private string $trace;

    public function __construct()
    {
        $this->trace = substr((string) Str::uuid(), 0, 8);
    }

    /**
     * Risolve il prezzo.
     *
     * @param  int                      $productId
     * @param  int|null                 $customerId  null = guest (usa prezzo base)
     * @param  Carbon|string|null       $date        null = oggi; accetta 'Y-m-d' o parsabile
     * @return array{
     *     price:string,
     *     source:string,           // 'customer' | 'base'
     *     version_id:?int,
     *     valid_from:?string,      // 'Y-m-d'
     *     valid_to:?string         // 'Y-m-d'
     * }|null
     */
    public function resolve(int $productId, ?int $customerId, Carbon|string|null $date = null): ?array
    {
        /* Log ingresso
        Log::debug('PriceResolver START', [
            'trace'       => $this->trace,
            'product_id'  => $productId,
            'customer_id' => $customerId,
            'date_raw'    => $date instanceof Carbon ? $date->toDateTimeString() : $date,
        ]);*/

        // Normalizzazione data
        $d = $this->normalizeDate($date);
        //Log::debug('PriceResolver date normalized', ['trace' => $this->trace, 'date' => $d]);

        // Chiavi memo/cache
        $memoKey  = "{$productId}:" . ($customerId ?? 'null') . ":{$d}";
        $cacheKey = "pricing:eff:{$memoKey}";

        // 1) Memo per-request
        if (array_key_exists($memoKey, $this->memo)) {
            Log::debug('PriceResolver MEMO HIT', [
                'trace'  => $this->trace,
                'memoKey'=> $memoKey,
                'result' => $this->safeResultForLog($this->memo[$memoKey]),
            ]);
            return $this->memo[$memoKey];
        }

        // 2) Micro-cache applicativa
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) { // ✅ solo risultati validi
            Log::debug('PriceResolver CACHE HIT (valid)', [
                'trace'    => $this->trace,
                'cacheKey' => $cacheKey,
                'result'   => $this->safeResultForLog($cached),
            ]);
            $this->memo[$memoKey] = $cached;
            return $cached;
        }
        if ($cached === null) {   // 🟡 ignora i null cacheati (non bloccare il calcolo)
            /*Log::debug('PriceResolver CACHE HIT (null ignored, will compute)', [
                'trace'    => $this->trace,
                'cacheKey' => $cacheKey,
            ]);*/
        }

        $result = null;

        // 3) Tentativo: versione cliente valida
        if ($customerId !== null) {
            // Conteggio rapido righe per (product, customer) per diagnosi
            $countAll = CustomerProductPrice::query()
                ->where('product_id', $productId)
                ->where('customer_id', $customerId)
                ->count();

            /*Log::debug('PriceResolver rows for (product,customer)', [
                'trace'       => $this->trace,
                'product_id'  => $productId,
                'customer_id' => $customerId,
                'rows'        => $countAll,
            ]);*/

            $row = CustomerProductPrice::query()
                ->where('product_id', $productId)
                ->where('customer_id', $customerId)
                ->where('valid_from', '<=', $d)
                ->where(function ($q) use ($d) {
                    $q->whereNull('valid_to')->orWhere('valid_to', '>=', $d);
                })
                ->orderByDesc('valid_from')
                ->first();

            if ($row) {
                /*Log::debug('PriceResolver MATCH customer version', [
                    'trace'      => $this->trace,
                    'row_id'     => $row->id,
                    'price'      => (string) $row->price,
                    'valid_from' => $this->toDateString($row->valid_from),
                    'valid_to'   => $this->toDateString($row->valid_to),
                ]);*/

                $result = [
                    'price'      => (string) $row->price,
                    'source'     => 'customer',
                    'version_id' => (int) $row->id,
                    'valid_from' => $this->toDateString($row->valid_from),
                    'valid_to'   => $this->toDateString($row->valid_to),
                ];
            } else {
                Log::debug('PriceResolver NO customer version for date', [
                    'trace' => $this->trace,
                    'date'  => $d,
                ]);
            }
        } else {
            Log::debug('PriceResolver customer_id is NULL → guest/base flow', ['trace' => $this->trace]);
        }

        // 4) Fallback: prezzo base del prodotto
        if ($result === null) {
            $product = Product::query()->select(['id', 'price'])->find($productId);

            if ($product && $product->price !== null) {
                /*Log::debug('PriceResolver FALLBACK to base price', [
                    'trace' => $this->trace,
                    'price' => (string) $product->price,
                ]);*/

                $result = [
                    'price'      => (string) $product->price,
                    'source'     => 'base',
                    'version_id' => null,
                    'valid_from' => null,
                    'valid_to'   => null,
                ];
            } else {
                Log::debug('PriceResolver NO base price', [
                    'trace'      => $this->trace,
                    'hasProduct' => (bool) $product,
                ]);
            }
        }

        // 5) Memo + cache (TTL breve). ❗ Non salviamo null in cache.
        $this->memo[$memoKey] = $result;
        if ($result !== null) {
            Cache::put($cacheKey, $result, now()->addSeconds(60));
        }

        /*Log::debug('PriceResolver END', [
            'trace'  => $this->trace,
            'result' => $this->safeResultForLog($result),
        ]);*/

        return $result;
    }

    /**
     * Normalizza la data in formato 'Y-m-d'.
     */
    private function normalizeDate(Carbon|string|null $date): string
    {
        if ($date instanceof Carbon) {
            return $date->copy()->startOfDay()->toDateString();
        }
        if (is_string($date) && $date !== '') {
            try {
                return Carbon::parse($date)->startOfDay()->toDateString();
            } catch (\Throwable $e) {
                // ricade su oggi
            }
        }
        return now()->startOfDay()->toDateString();
    }

    /**
     * Converte un campo data (string|Carbon|null) in 'Y-m-d' sicuro per il log/JSON.
     */
    private function toDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            return ($value instanceof Carbon)
                ? $value->toDateString()
                : Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Serializza il risultato per il log evitando Model/oggetti.
     *
     * @param  mixed $result
     * @return array<string,mixed>|null
     */
    private function safeResultForLog(mixed $result): ?array
    {
        if ($result === null) return null;

        return [
            'price'      => $result['price']      ?? null,
            'source'     => $result['source']     ?? null,
            'version_id' => $result['version_id'] ?? null,
            'valid_from' => $result['valid_from'] ?? null,
            'valid_to'   => $result['valid_to']   ?? null,
        ];
    }
}