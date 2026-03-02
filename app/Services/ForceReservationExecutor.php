<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Services\ProcurementService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * ForceReservationExecutor
 * ---------------------------------------------------------------------
 * Applica realmente il piano di riallocazione calcolato dal Planner:
 *
 * A) Prenota da giacenza disponibile (free stock)
 * B) Sposta prenotazioni da ordini penalizzati (donatori) all'ordine urgente
 *
 * Vincoli:
 * - Tutto deve avvenire in transazione e con lock pessimisti, per evitare race-condition.
 * - Se durante il commit scopriamo che non c'è più disponibilità reale → rollback e blocco.
 * - Dopo aver penalizzato un ordine, generiamo ProcurementService per coprire la nuova mancanza
 *   (se l'utente ha permesso orders.supplier.create).
 */
final class ForceReservationExecutor
{
    /**
     * Esegue il commit del piano (atomico).
     *
     * @param OrderItem $urgentItem Riga d'ordine che sta tentando di avanzare.
     * @param float $moveQty Quantità avanzamento (solo per note/log).
     * @param array<string,mixed> $plan Piano prodotto dal ForceReservationPlanner.
     * @param Authenticatable $user Utente corrente (permessi / tracciamento).
     *
     * @return array{
     *   donor_orders_touched: array<int,int>,
     *   procurement_po_numbers: \Illuminate\Support\Collection<string>
     * }
     */
    public function execute(
        OrderItem $urgentItem,
        float $moveQty,
        array $plan,
        Authenticatable $user
    ): array {
        return DB::transaction(function () use ($urgentItem, $moveQty, $plan, $user): array {

            // 1) Lock riga ordine urgente: non vogliamo che qualcuno cambi stato mentre riallochiamo
            /** @var OrderItem $item */
            $item = OrderItem::whereKey($urgentItem->id)->lockForUpdate()->firstOrFail();
            $item->loadMissing('order');

            /** @var Order $urgentOrder */
            $urgentOrder = $item->order;

            Log::info('[ForceReservationExecutor] START', [
                'urgent_order_id' => $urgentOrder->id,
                'order_item_id'   => $item->id,
                'move_qty'        => $moveQty,
            ]);

            // 2) Normalizza e valida piano minimo
            $missingRows = collect($plan['missing'] ?? []);
            $fromFree    = (array) ($plan['from_free'] ?? []);
            $fromDonors  = collect($plan['from_donors'] ?? []);

            if ($missingRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'stock' => 'Piano riallocazione non valido: componenti mancanti assenti.',
                ]);
            }

            // 3) Costruisci elenco stock_level_id da lockare (ordine stabile per ridurre deadlock)
            $stockLevelIds = collect();

            foreach ($fromFree as $componentId => $allocs) {
                foreach ((array) $allocs as $a) {
                    $stockLevelIds->push((int) $a['stock_level_id']);
                }
            }

            foreach ($fromDonors as $d) {
                $stockLevelIds->push((int) $d['stock_level_id']);
            }

            $stockLevelIds = $stockLevelIds->unique()->sort()->values();

            // 4) Lock stock_levels coinvolti (ordine crescente)
            if ($stockLevelIds->isNotEmpty()) {
                StockLevel::whereIn('id', $stockLevelIds->all())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            // 5) Lock stock_reservations donatori (ordine crescente)
            $donorReservationIds = $fromDonors
                ->pluck('stock_reservation_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();

            if ($donorReservationIds->isNotEmpty()) {
                StockReservation::whereIn('id', $donorReservationIds->all())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            // 6) Applica STEP A: prenotazione da giacenza libera (ricontrollo free reale)
            foreach ($fromFree as $componentId => $allocs) {
                foreach ((array) $allocs as $a) {

                    $stockLevelId = (int) $a['stock_level_id'];
                    $qty          = (float) $a['qty'];

                    if ($qty <= 0) {
                        continue;
                    }

                    // Lock “logico” sulle reservations di quel livello per calcolare free in modo consistente
                    $reserved = (float) StockReservation::where('stock_level_id', $stockLevelId)
                        ->lockForUpdate()
                        ->sum('quantity');

                    $sl = StockLevel::whereKey($stockLevelId)->lockForUpdate()->firstOrFail();

                    $free = max((float) $sl->quantity - $reserved, 0.0);

                    if ($free + 1e-6 < $qty) {
                        throw ValidationException::withMessages([
                            'stock' => "Durante la conferma la giacenza disponibile è cambiata: non posso prenotare {$qty} unità (disponibili {$free}). Ripeti l’operazione.",
                        ]);
                    }

                    // Prenota sull'ordine urgente (upsert con lock)
                    $this->increaseReservation($urgentOrder->id, $stockLevelId, $qty);

                    // Movimento di prenotazione (coerente con il tuo storico) :contentReference[oaicite:1]{index=1}
                    StockMovement::create([
                        'stock_level_id' => $stockLevelId,
                        'type'           => 'reserve',
                        'quantity'       => $qty,
                        'note'           => "Forza prenotazione: giacenza disponibile → OC #{$urgentOrder->id}",
                    ]);
                }
            }

            // 7) Applica STEP B: sposta prenotazioni da ordini penalizzati (donatori)
            //    Accumuliamo le quantità “tolte” per poi generare Procurement sul donatore.
            $donorDecrease = []; // [donor_order_id => [component_id => qty]]

            foreach ($fromDonors as $d) {

                $donorOrderId = (int) $d['donor_order_id'];
                $componentId  = (int) $d['component_id'];
                $stockResId   = (int) $d['stock_reservation_id'];
                $stockLevelId = (int) $d['stock_level_id'];
                $qty          = (float) $d['qty'];

                if ($qty <= 0) {
                    continue;
                }

                // Non ha senso “rubare” da noi stessi
                if ($donorOrderId === (int) $urgentOrder->id) {
                    continue;
                }

                /** @var StockReservation $donorRes */
                $donorRes = StockReservation::whereKey($stockResId)->lockForUpdate()->firstOrFail();

                if ((float) $donorRes->quantity + 1e-6 < $qty) {
                    throw ValidationException::withMessages([
                        'stock' => "Durante la conferma una prenotazione donatrice è cambiata: non posso riallocare {$qty} unità. Ripeti l’operazione.",
                    ]);
                }

                // 7.1 decremento donatore (se scende a 0, elimino riga)
                $left = (float) $donorRes->quantity - $qty;

                if ($left <= 1e-6) {
                    $donorRes->delete();
                } else {
                    $donorRes->quantity = $left;
                    $donorRes->save();
                }

                StockMovement::create([
                    'stock_level_id' => $stockLevelId,
                    'type'           => 'unreserve',
                    'quantity'       => $qty,
                    'note'           => "Forza prenotazione: riallocato da OC #{$donorOrderId} → OC #{$urgentOrder->id}",
                ]);

                // 7.2 incremento urgente sullo stesso stock_level_id
                $this->increaseReservation($urgentOrder->id, $stockLevelId, $qty);

                StockMovement::create([
                    'stock_level_id' => $stockLevelId,
                    'type'           => 'reserve',
                    'quantity'       => $qty,
                    'note'           => "Forza prenotazione: riallocato da OC #{$donorOrderId} → OC #{$urgentOrder->id}",
                ]);

                // 7.3 accumulo shortage donatore per procurement
                $donorDecrease[$donorOrderId][$componentId] = ($donorDecrease[$donorOrderId][$componentId] ?? 0.0) + $qty;
            }

            // 8) Controllo “di coerenza”: per ogni componente mancante deve risultare coperto dal piano
            //    (non ricalcoliamo tutta la BOM: ci basta verificare che abbiamo applicato le qty pianificate)
            //    Se qui vuoi un check più forte, in futuro potremo ricalcolare checkReservations().
            //    Per ora: il planner aveva garantito copertura 100% e qui abbiamo applicato con guard-rails.
            $this->assertPlanCoverageOrFail($missingRows, $fromFree, $fromDonors);

            // 9) Procurement sugli ordini penalizzati (uno per ordine)
            //    Stesso pattern del rollback scrap: buildShortageCollection + fromShortage :contentReference[oaicite:2]{index=2}
            $poNumbers = collect();

            foreach ($donorDecrease as $donorOrderId => $componentsQty) {

                // Se l'utente non può creare PO, blocchiamo: altrimenti penalizzi e basta (non desiderato)
                if (! $user->can('orders.supplier.create')) {
                    throw ValidationException::withMessages([
                        'stock' => 'Non hai i permessi per generare l’approvvigionamento degli ordini penalizzati.',
                    ]);
                }

                $shortageColl = collect($componentsQty)
                    ->map(fn ($q, $cid) => ['component_id' => (int) $cid, 'shortage' => (float) $q])
                    ->values();

                $shortageColl = ProcurementService::buildShortageCollection($shortageColl);
                $procResult   = ProcurementService::fromShortage($shortageColl, (int) $donorOrderId);

                $poNumbers = $poNumbers->merge($procResult['po_numbers'] ?? collect());
            }

            $donorOrdersTouched = array_map('intval', array_keys($donorDecrease));

            Log::info('[ForceReservationExecutor] COMMIT OK', [
                'urgent_order_id'       => $urgentOrder->id,
                'donor_orders_touched'  => $donorOrdersTouched,
                'po_numbers'            => $poNumbers->values()->all(),
            ]);

            return [
                'donor_orders_touched'   => $donorOrdersTouched,
                'procurement_po_numbers' => $poNumbers->values(),
            ];
        });
    }

    /**
     * Incrementa (o crea) una prenotazione stock_reservations per (order_id, stock_level_id).
     *
     * @param int $orderId
     * @param int $stockLevelId
     * @param float $qty
     * @return void
     */
    private function increaseReservation(int $orderId, int $stockLevelId, float $qty): void
    {
        // Lock riga se esiste per evitare increment concorrenti
        $sr = StockReservation::where('order_id', $orderId)
            ->where('stock_level_id', $stockLevelId)
            ->lockForUpdate()
            ->first();

        if ($sr) {
            $sr->quantity = (float) $sr->quantity + $qty;
            $sr->save();
            return;
        }

        StockReservation::create([
            'stock_level_id' => $stockLevelId,
            'order_id'       => $orderId,
            'quantity'       => $qty,
        ]);
    }

    /**
     * Verifica che il piano copra tutti i mancanti (somma A+B per componente >= missing).
     *
     * @param Collection<int,array<string,mixed>> $missingRows
     * @param array<int,mixed> $fromFree
     * @param Collection<int,array<string,mixed>> $fromDonors
     * @throws ValidationException
     */
    private function assertPlanCoverageOrFail(Collection $missingRows, array $fromFree, Collection $fromDonors): void
    {
        foreach ($missingRows as $m) {
            $cid     = (int) $m['component_id'];
            $code    = (string) $m['code'];
            $missing = (float) $m['missing'];

            if ($missing <= 0) {
                continue;
            }

            $a = 0.0; // giacenza libera
            foreach (($fromFree[$cid] ?? []) as $row) {
                $a += (float) ($row['qty'] ?? 0);
            }

            $b = (float) $fromDonors
                ->where('component_id', $cid)
                ->sum('qty');

            if (($a + $b) + 1e-6 < $missing) {
                throw ValidationException::withMessages([
                    'stock' => "Piano non coerente: il componente {$code} non risulta coperto integralmente.",
                ]);
            }
        }
    }
}