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
    public function createForOrderItem(int $orderItemId, Authenticatable $user, ?array $requestedQtyByItemId = null): Ddt
    {
        return DB::transaction(function () use ($orderItemId, $user, $requestedQtyByItemId): Ddt {

            /* 1) Recupera la riga e risali all’ordine (lock per concorrenza) */
            $seedItem = OrderItem::query()
                ->with(['order.orderNumber', 'order.customer', 'order.occasionalCustomer'])
                ->lockForUpdate()
                ->findOrFail($orderItemId);

            $order = $seedItem->order;

            if (! $order) {
                throw ValidationException::withMessages([
                    'order' => 'Ordine non trovato per la riga selezionata.',
                ]);
            }

            /* 1 bis) Lock dell'ordine: evita corse tra due utenti che generano DDT insieme */
            Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            /* 2) Righe in fase 6 (Spedizione) con qty_in_phase > 0 */
            $phase = 6;

            $phaseRows = DB::table('v_order_item_phase_qty as v')
                ->join('order_items as oi', 'oi.id', '=', 'v.order_item_id')
                ->where('oi.order_id', $order->id)
                ->where('v.phase', $phase)
                ->where('v.qty_in_phase', '>', 0)
                ->select('v.order_item_id', 'v.qty_in_phase')
                ->get();

            if ($phaseRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'ddt' => 'Nessuna riga in Spedizione con quantità disponibile: impossibile generare il DDT.',
                ]);
            }

            /* 3) Quantità già emesse in DDT precedenti per questo ordine (per order_item) */
            $alreadyByItem = $this->alreadyDdtQtyByOrderItem($order->id); // [order_item_id => qty_emessa]

            /* 4) Calcola le quantità "nuove" da inserire nel DDT (delta) */
            $toShip = collect(); // [{order_item_id, qty}]

            foreach ($phaseRows as $r) {
                $itemId   = (int) $r->order_item_id;
                $inPhase  = (float) $r->qty_in_phase;
                $already  = (float) ($alreadyByItem[$itemId] ?? 0.0);

                /* Delta: quanto è veramente nuovo */
                $available = $inPhase - $already;

                /* Clamp per evitare negativi (es. rollback dopo DDT): in quel caso non aggiungiamo */
                if ($available <= 1e-6) {
                    continue;
                }

                /* Se in futuro vuoi split manuale: rispetta $requestedQtyByItemId */
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
             * - NON generiamo un nuovo numero DDT
             * - ritorniamo l'ultimo DDT dell'ordine (così il bottone "stampa" ristampa quello)
             *
             * Questo implementa la tua regola: "per ogni evasione non possiamo generare più di un DDT con numero diverso".
             */
            if ($toShip->isEmpty()) {
                $last = Ddt::query()
                    ->where('order_id', $order->id)
                    ->orderByDesc('issued_at')
                    ->orderByDesc('id')
                    ->first();

                if ($last) {
                    return $last->fresh([
                        'rows.orderItem.product',
                        'order.orderNumber',
                        'order.customer',
                        'order.occasionalCustomer',
                    ]);
                }

                throw ValidationException::withMessages([
                    'ddt' => 'Nessuna nuova quantità da spedire: nessun DDT generabile.',
                ]);
            }

            /* 6) Progressivo annuale (retry su collisione UNIQUE(year, number)) */
            $today = Carbon::today();
            $year  = (int) $today->format('Y');

            $ddt = $this->createHeaderWithRetry($order->id, $year, $today, $user);

            /* 7) Carica gli OrderItem coinvolti */
            $items = OrderItem::query()
                ->with(['product'])
                ->whereIn('id', $toShip->pluck('order_item_id')->all())
                ->get()
                ->keyBy('id');

            /* 8) Crea righe DDT (snapshot qty + prezzo) */
            foreach ($toShip as $r) {
                $it = $items->get((int) $r->order_item_id);

                if (! $it) {
                    continue; // riga sparita? non blocchiamo l’intero documento
                }

                DdtRow::create([
                    'ddt_id' => $ddt->id,
                    'order_item_id' => $it->id,
                    'quantity' => (float) $r->qty,
                    'unit_price' => (float) ($it->unit_price ?? 0),
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
     * Totale già emesso in DDT per ogni order_item dell'ordine.
     *
     * @return array<int,float> [order_item_id => qty_emessa]
     */
    private function alreadyDdtQtyByOrderItem(int $orderId): array
    {
        return DB::table('ddt_rows as dr')
            ->join('ddts as d', 'd.id', '=', 'dr.ddt_id')
            ->where('d.order_id', $orderId)
            ->groupBy('dr.order_item_id')
            ->select('dr.order_item_id', DB::raw('SUM(dr.quantity) as qty'))
            ->pluck('qty', 'order_item_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Crea header DDT con retry su collisione progressivo annuale.
     */
    private function createHeaderWithRetry(int $orderId, int $year, Carbon $issuedAt, Authenticatable $user): Ddt
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
