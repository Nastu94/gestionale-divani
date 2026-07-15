<?php

namespace App\Services\Ddt;

use App\Models\Ddt;
use App\Models\DdtRow;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

/**
 * Service DDT:
 * - crea un DDT progressivo annuale
 * - inserisce righe prendendo la quantità "spedibile" in fase 6 (Spedizione)
 *   MA sottraendo quanto già inserito in DDT precedenti dello stesso ordine.
 *
 * Problema risolto:
 * - Se un ordine viene evaso in più tranche (4 pezzi + 6 pezzi), non dobbiamo
 *   far risultare 10 la seconda volta (che diventerebbe 14 totali). Quindi:
 *   qty_to_ship = qty_in_phase(6) - sum(ddt_rows.quantity già emesse per quell'order_item).
 */
class DdtService
{
    /**
     * Crea (o recupera) un DDT per l'ordine collegato alla riga ordine selezionata.
     *
     * Nota importante (assunzione esplicita):
     * - Questo metodo, ad oggi, "spedisci tutto ciò che è nuovo" in fase 6.
     * - Se in futuro vuoi scegliere manualmente quante unità spedire (split volontario),
     *   aggiungeremo un modal e passeremo un array qty per riga.
     *
     * @param int $orderItemId ID riga ordine cliccata.
     * @param Authenticatable $user Utente che genera il DDT.
     * @param array<int,float>|null $requestedQtyByItemId (opzionale) quantità richieste per riga.
     */
    public function createForOrderItem(
        int $orderItemId,
        Authenticatable $user,
        ?array $requestedQtyByItemId = null,
        ?array $unitPriceOverrideByItemId = null
    ): Ddt
    {
        // DDT singolo: prendiamo tutte le righe di questo ordine attualmente in spedizione.
        $item = OrderItem::findOrFail($orderItemId);
        $orderItemIds = \Illuminate\Support\Facades\DB::table('v_order_item_phase_qty')
            ->join('order_items', 'order_items.id', '=', 'v_order_item_phase_qty.order_item_id')
            ->where('order_items.order_id', $item->order_id)
            ->where('v_order_item_phase_qty.phase', \App\Enums\ProductionPhase::SHIPPING->value)
            ->where('v_order_item_phase_qty.qty_in_phase', '>', 0)
            ->pluck('v_order_item_phase_qty.order_item_id')
            ->toArray();

        // Fallback di sicurezza: includiamo almeno la riga passata
        if (empty($orderItemIds)) {
            $orderItemIds = [$orderItemId];
        }

        return $this->createAccorpato(
            $orderItemIds,
            $user,
            $requestedQtyByItemId,
            $unitPriceOverrideByItemId
        );
    }

    /**
     * Crea (o recupera) un DDT per più righe ordine (accorpamento).
     */
    public function createAccorpato(
        array $orderItemIds,
        Authenticatable $user,
        ?array $requestedQtyByItemId = null,
        ?array $unitPriceOverrideByItemId = null
    ): Ddt
    {
        return DB::transaction(function () use (
            $orderItemIds,
            $user,
            $requestedQtyByItemId,
            $unitPriceOverrideByItemId
        ): Ddt {

            /* 1) Recupera le righe e risali agli ordini (lock per concorrenza) */
            $orderItemIds = collect($orderItemIds)->unique()->filter()->values()->all();

            $items = OrderItem::query()
                ->with(['order.orderNumber', 'order.customer', 'order.occasionalCustomer'])
                ->lockForUpdate()
                ->whereIn('id', $orderItemIds)
                ->get()
                ->keyBy('id');

            if ($items->isEmpty() || $items->count() !== count($orderItemIds)) {
                throw ValidationException::withMessages([
                    'order' => 'Una o più righe ordine non trovate per la selezione effettuata.',
                ]);
            }

            $orders = $items->pluck('order')->unique('id')->values();

            // Validazione accorpato: stesso cliente o destinazione
            $firstOrder = $orders->first();
            $firstCustomerKey = $firstOrder->customer_id !== null
                ? 'customer:' . $firstOrder->customer_id
                : 'occasional:' . $firstOrder->occasional_customer_id;
            
            $firstShippingZone = $firstOrder->shipping_zone;
            $firstShippingAddress = $firstOrder->shipping_address;

            foreach ($orders as $o) {
                $customerKey = $o->customer_id !== null
                    ? 'customer:' . $o->customer_id
                    : 'occasional:' . $o->occasional_customer_id;

                if ($customerKey !== $firstCustomerKey) {
                    throw ValidationException::withMessages([
                        'ddt' => 'Impossibile accorpare righe di clienti diversi.',
                    ]);
                }

                if ($o->shipping_zone !== $firstShippingZone || $o->shipping_address !== $firstShippingAddress) {
                    throw ValidationException::withMessages([
                        'ddt' => 'Impossibile accorpare righe con destinazioni diverse.',
                    ]);
                }
            }

            /* 1 bis) Lock degli ordini */
            Order::query()
                ->whereIn('id', $orders->pluck('id')->all())
                ->lockForUpdate()
                ->get();

            /* 2) Righe in fase 3 (Spedizione) con qty_in_phase > 0 */
            $phase = \App\Enums\ProductionPhase::SHIPPING->value;

            $phaseRows = DB::table('v_order_item_phase_qty as v')
                ->join('order_items as oi', 'oi.id', '=', 'v.order_item_id')
                ->whereIn('oi.id', $orderItemIds)
                ->where('v.phase', $phase)
                ->where('v.qty_in_phase', '>', 0)
                ->select('v.order_item_id', 'v.qty_in_phase')
                ->get();

            if ($phaseRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'ddt' => 'Nessuna riga in Spedizione con quantità disponibile: impossibile generare il DDT.',
                ]);
            }

            /* 3) Quantità già emesse in DDT precedenti (per order_item) */
            $alreadyByItem = $this->alreadyDdtQtyByOrderItems($orderItemIds);

            /* 4) Calcola le quantità "nuove" da inserire nel DDT (delta) */
            $toShip = collect();

            foreach ($phaseRows as $r) {
                $itemId   = (int) $r->order_item_id;
                $inPhase  = (float) $r->qty_in_phase;
                $already  = (float) ($alreadyByItem[$itemId] ?? 0.0);

                /* Delta: quanto è veramente nuovo */
                $available = $inPhase - $already;

                if ($available <= 1e-6) {
                    continue;
                }

                $qty = $available;
                if (is_array($requestedQtyByItemId) && array_key_exists($itemId, $requestedQtyByItemId)) {
                    $req = (float) $requestedQtyByItemId[$itemId];
                    $qty = max(min($qty, $req), 0);
                }

                if ($qty > 1e-6) {
                    $toShip->push((object) [
                        'order_item_id' => $itemId,
                        'qty' => $qty,
                    ]);
                }
            }

            /**
             * 5) Se non c'è nulla di nuovo da spedire:
             */
            if ($toShip->isEmpty()) {
                throw ValidationException::withMessages([
                    'ddt' => 'Le righe selezionate non hanno quantità residue da documentare.',
                ]);
            }

            // Per intestazione: usiamo il primo ordine selezionato
            // Ordine capofila deterministico (il più vecchio)
            $firstOrder = $orders->sortBy('created_at')->first();

            /* 6) Progressivo annuale e data DDT basati sulla data reale di emissione */
            $issuedAt = Carbon::now(config('app.timezone', 'Europe/Rome'))->startOfDay();
            $year  = (int) $issuedAt->format('Y');

            // Somma packages di tutti gli ordini accorpati
            $totalPackages = $orders->contains(fn ($order) => $order->packages !== null)
                ? $orders->sum(fn ($order) => (int) ($order->packages ?? 0))
                : null;

            $ddt = $this->createHeaderWithRetry($firstOrder->id, $year, $issuedAt, $user, $totalPackages);

            /* 7) Crea righe DDT (snapshot qty + prezzo) */
            foreach ($toShip as $r) {
                $it = $items->get((int) $r->order_item_id);

                if (! $it) {
                    continue; // riga sparita? non blocchiamo l’intero documento
                }

                /**
                 * Prezzo snapshot della riga DDT.
                 *
                 * Se è stato fornito un override per la riga corrente, usiamo quello.
                 * Altrimenti manteniamo il prezzo originale della riga ordine.
                 */
                $unitPrice = (is_array($unitPriceOverrideByItemId) && array_key_exists($it->id, $unitPriceOverrideByItemId))
                    ? (float) $unitPriceOverrideByItemId[$it->id]
                    : (float) ($it->unit_price ?? 0);

                DdtRow::create([
                    'ddt_id' => $ddt->id,
                    'order_item_id' => $it->id,
                    'quantity' => (float) $r->qty,
                    'unit_price' => $unitPrice,
                    'vat' => 22,
                ]);
            }

            return $ddt->fresh([
                'rows.orderItem.product',
                'order.orderNumber',
                'order.customer',
                'order.occasionalCustomer',
            ]);
        });
    }

    /**
     * Totale già emesso in DDT per gli order_items
     *
     * @return array<int,float> [order_item_id => qty_emessa]
     */
    private function alreadyDdtQtyByOrderItems(array $orderItemIds): array
    {
        return DB::table('ddt_rows')
            ->whereIn('order_item_id', $orderItemIds)
            ->groupBy('order_item_id')
            ->select('order_item_id', DB::raw('SUM(quantity) as qty'))
            ->pluck('qty', 'order_item_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Crea header DDT con retry su collisione progressivo annuale.
     */
    private function createHeaderWithRetry(int $orderId, int $year, Carbon $issuedAt, Authenticatable $user, ?int $packages = null): Ddt
    {
        $attempts = 0;

        while ($attempts < 5) {
            $attempts++;

            $nextNumber = $this->nextNumberForYear($year);

            try {
                return Ddt::create([
                    'order_id' => $orderId,
                    'year' => $year,
                    'number' => $nextNumber,
                    'issued_at' => $issuedAt,

                    /* Default “proforma”: poi li renderai editabili */
                    'carrier_name' => 'conserva s.p.a.',
                    'transport_reason' => 'C/Vendita con scontrino',
                    'packages' => $packages,
                    'port' => 'Porto Franco',
                    'created_by' => $user->getAuthIdentifier(),
                ]);
            } catch (QueryException $e) {
                /* Duplicate key: ritenta incrementando */
                if ($this->isDuplicateKey($e)) {
                    continue;
                }
                throw $e;
            }
        }

        throw ValidationException::withMessages([
            'ddt' => 'Impossibile generare un numero DDT univoco. Riprova.',
        ]);
    }

    /**
     * Prossimo numero DDT per anno.
     */
    private function nextNumberForYear(int $year): int
    {
        $max = Ddt::query()
            ->where('year', $year)
            ->max('number');

        return ((int) $max) + 1;
    }

    /**
     * Rileva errore di duplicate key (MySQL).
     */
    private function isDuplicateKey(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null; // es. 23000
        $errCode  = $e->errorInfo[1] ?? null; // es. 1062
        return ($sqlState === '23000' && (int)$errCode === 1062);
    }
}
