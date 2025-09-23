<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\OrderItemPhaseEvent;
use App\Models\ProductStockLevel;
use App\Models\Warehouse;
use App\Enums\ProductionPhase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReturnedProductReservationService
{
    /**
     * Tenta di coprire $qty dell’item usando giacenze “prodotti finiti” dal magazzino resi.
     * - Marca le righe ProductStockLevel come riservate (reserved_for = order_id).
     * - Se necessario, splitta la riga (quando qty riga > qty richiesta e NON esiste reserved_qty).
     * - Registra un evento in order_item_phase_events per la quantità riservata (inserito → spedito).
     *
     * @return array{ reserved: float, missing: float, levels: \Illuminate\Support\Collection<int> }
     */
    public function reserveForItem(
        OrderItem $item,
        float $qty,
        Authenticatable $user,
        ?int $destinationPhase = null,          // opzionale: override fase "spedizione"
        ?int $returnsWarehouseId = null         // opzionale: override magazzino resi
    ): array {
        return DB::transaction(function () use ($item, $qty, $user, $destinationPhase, $returnsWarehouseId) {

            $qtyRequested = max(0.0, (float) $qty);
            $qtyLeft      = $qtyRequested;
            $qtyReserved  = 0.0;

            // 1) Magazzino resi
            $returnsWh = $returnsWarehouseId
                ? Warehouse::lockForUpdate()->findOrFail($returnsWarehouseId)
                : Warehouse::lockForUpdate()
                    ->where('type', 'return')
                    ->orWhere('code', 'MG-RETURN')
                    ->firstOrFail();

            // 2) Caratteristiche prodotto da rispettare (stesso prodotto + stesso FC se presente)
            $fabricId = $item->variable->fabric_id ?? null;
            $colorId  = $item->variable->color_id  ?? null;

            // 3) Cerca righe disponibili (non già riservate) FIFO
            /** @var Collection<int,ProductStockLevel> $levels */
            $levels = ProductStockLevel::query()
                ->where('warehouse_id', $returnsWh->id)
                ->where('product_id',  $item->product_id)
                ->whereNull('reserved_for')
                ->when($fabricId, fn($q) => $q->where('fabric_id', $fabricId))
                ->when($colorId,  fn($q) => $q->where('color_id',  $colorId))
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $usedLevels = collect();

            foreach ($levels as $level) {
                if ($qtyLeft <= 1e-6) break;

                $take = min($level->quantity, $qtyLeft);

                // Se la riga è "più grande" di quello che ci serve e NON hai un campo reserved_qty,
                // splittiamo la riga in due, riservando la clone.
                if ($level->quantity - $take > 1e-6) {
                    // riduci la riga originale
                    $level->quantity = $level->quantity - $take;
                    $level->save();

                    // crea la riga "reservata"
                    $reservedRow = ProductStockLevel::create([
                        'product_id'   => $level->product_id,
                        'fabric_id'    => $level->fabric_id,
                        'color_id'     => $level->color_id,
                        'warehouse_id' => $level->warehouse_id,
                        'quantity'     => $take,
                        'reserved_for' => $item->order_id,   // 👈 prenotata per l’OC
                        // IMPORTANTE: order_id rimane quello di ORIGINE (l’ordine del reso)
                        'order_id'     => $level->order_id,
                    ]);

                    $usedLevels->push($reservedRow->id);
                } else {
                    // riserviamo tutta la riga
                    $level->reserved_for = $item->order_id;
                    $level->save();

                    $usedLevels->push($level->id);
                }

                $qtyReserved += $take;
                $qtyLeft     -= $take;
            }

            // 4) Se abbiamo riservato qualcosa → registra evento fase "inserito → spedito"
            if ($qtyReserved > 1e-6) {
                // Determina la fase "spedizione"
                $toPhase = $destinationPhase
                    ?? (defined('\App\Enums\ProductionPhase::Shipping')
                        ? ProductionPhase::Shipping->value
                        : 6); // fallback numerico, aggiorna se necessario

                $fromPhase = $item->current_phase->value ?? 0;

                OrderItemPhaseEvent::create([
                    'order_item_id' => $item->id,
                    'from_phase'    => $fromPhase,
                    'to_phase'      => $toPhase,
                    'quantity'      => $qtyReserved,
                    'changed_by'    => $user->id,
                    'is_rollback'   => false,
                    'reason'        => null,
                    'operator'      => null,
                ]);

                Log::info('[ReturnedProductReservationService] reserved from returns', [
                    'order_item' => $item->id,
                    'requested'  => $qtyRequested,
                    'reserved'   => $qtyReserved,
                    'levels'     => $usedLevels->all(),
                    'from'       => $fromPhase,
                    'to'         => $toPhase,
                ]);
            } else {
                Log::info('[ReturnedProductReservationService] no returns stock available', [
                    'order_item' => $item->id,
                    'requested'  => $qtyRequested,
                ]);
            }

            return [
                'reserved' => $qtyReserved,
                'missing'  => max($qtyRequested - $qtyReserved, 0),
                'levels'   => $usedLevels,
            ];
        });
    }

    /**
     * Identifica il magazzino resi, se possibile.
     * 1) Da config (inventory.return_warehouse_id)
     * 2) Da flag is_returns in tabella warehouses
     * 3) Da nome (contiene "reso", "resi", "return")
     *
     * @return int|null ID del magazzino resi, o null se non identificabile
     */
    protected function returnsWarehouseId(): ?int
    {
        // 1) da config
        $cfg = config('inventory.return_warehouse_id');
        if ($cfg !== null) return (int) $cfg;

        // 2) flag in tabella warehouses
        if (Schema::hasTable('warehouses')) {
            if (Schema::hasColumn('warehouses', 'is_returns')) {
                $id = DB::table('warehouses')->where('is_returns', 1)->value('id');
                if ($id) return (int) $id;
            }
            // 3) fallback per nome
            $id = DB::table('warehouses')
                ->where(function($q){
                    $q->where('name', 'like', '%reso%')
                    ->orWhere('name', 'like', '%resi%')
                    ->orWhere('name', 'like', '%return%');
                })
                ->value('id');
            if ($id) return (int) $id;
        }

        return null; // se non troviamo niente, non filtriamo per warehouse
    }

    /**
     * Dry-run: quanta qty posso coprire coi prodotti finiti già a stock nel magazzino resi
     * (e, opzionalmente, con righe reso legacy). Nessuna scrittura.
     */
    public function dryRunCover(
        int $productId,
        float $quantity,
        ?int $fabricId = null,
        ?int $colorId  = null,
        ?int $excludeOrderId = null
    ): float {
        $available = $this->availableReturnsQty($productId, $fabricId, $colorId, $excludeOrderId);
        return (float) min($quantity, max($available, 0.0));
    }

    /**
     * Disponibilità “copribile” da resi: somma da product_stock_levels (magazzino resi)
     * al netto delle prenotazioni (reserved_for). In più, se presenti, considera eventuali
     * tabelle di righe-reso/prenotazioni reso (compatibilità con set-up precedenti).
     */
    public function availableReturnsQty(
        int $productId,
        ?int $fabricId = null,
        ?int $colorId  = null,
        ?int $excludeOrderId = null
    ): float {
        $total = 0.0;

        /* ========= Fonte A: product_stock_levels (magazzino resi) ========= */
        if (Schema::hasTable('product_stock_levels')) {
            $q = DB::table('product_stock_levels as psl')
                ->where('psl.product_id', $productId);

            if (Schema::hasColumn('product_stock_levels', 'fabric_id')) {
                if ($fabricId !== null) $q->where('psl.fabric_id', $fabricId);
                else                    $q->whereNull('psl.fabric_id');
            }
            if (Schema::hasColumn('product_stock_levels', 'color_id')) {
                if ($colorId !== null)  $q->where('psl.color_id', $colorId);
                else                    $q->whereNull('psl.color_id');
            }

            // limita al WAREHOUSE resi se identificabile
            $whId = $this->returnsWarehouseId();
            if ($whId !== null && Schema::hasColumn('product_stock_levels', 'warehouse_id')) {
                $q->where('psl.warehouse_id', $whId);
            }

            // solo “liberi” o già riservati per questo OC (in edit)
            if (Schema::hasColumn('product_stock_levels', 'reserved_for')) {
                $q->where(function($w) use ($excludeOrderId) {
                    $w->whereNull('reserved_for');
                    if ($excludeOrderId) $w->orWhere('reserved_for', $excludeOrderId);
                });
            }

            $total += (float) $q->sum('psl.quantity');
        }

        /* ========= Fonte B (opzionale): tabelle legacy dei resi ========= */
        $tblReturns         = Schema::hasTable('customer_returns') ? 'customer_returns' : (Schema::hasTable('returns') ? 'returns' : null);
        $tblReturnLines     = Schema::hasTable('product_return_lines') ? 'product_return_lines' :
                            (Schema::hasTable('return_lines') ? 'return_lines' : null);
        $tblResReservations = Schema::hasTable('returned_product_reservations') ? 'returned_product_reservations' :
                            (Schema::hasTable('return_reservations') ? 'return_reservations' : null);
        $tblOrderItems      = Schema::hasTable('order_items') ? 'order_items' : null;

        if ($tblReturnLines) {
            // totale righe reso “stockabili”
            $base = DB::table("$tblReturnLines as rl")
                ->when($tblReturns, fn($q) => $q->join("$tblReturns as r", 'r.id', '=', 'rl.return_id'))
                ->where('rl.product_id', $productId)
                ->when(Schema::hasColumn($tblReturnLines, 'restock'), fn($q) => $q->where('rl.restock', 1))
                ->when(Schema::hasColumn($tblReturnLines, 'deleted_at'), fn($q) => $q->whereNull('rl.deleted_at'))
                ->when($tblReturns && Schema::hasColumn($tblReturns, 'deleted_at'), fn($q) => $q->whereNull('r.deleted_at'));

            if (Schema::hasColumn($tblReturnLines, 'fabric_id')) {
                if ($fabricId !== null) $base->where('rl.fabric_id', $fabricId);
                else                    $base->whereNull('rl.fabric_id');
            }
            if (Schema::hasColumn($tblReturnLines, 'color_id')) {
                if ($colorId !== null)  $base->where('rl.color_id', $colorId);
                else                    $base->whereNull('rl.color_id');
            }

            $fromLines = (float) $base->clone()->selectRaw('COALESCE(SUM(rl.quantity),0) as qty')->value('qty');

            // prenotazioni su quelle righe reso
            $reservedOnLines = 0.0;
            if ($tblResReservations && Schema::hasColumn($tblResReservations, 'return_line_id') && Schema::hasColumn($tblResReservations, 'quantity')) {
                $resQ = DB::table("$tblResReservations as rr")
                    ->join("$tblReturnLines as rl", 'rl.id', '=', 'rr.return_line_id')
                    ->when($tblReturns, fn($q) => $q->join("$tblReturns as r", 'r.id', '=', 'rl.return_id'))
                    ->where('rl.product_id', $productId)
                    ->when(Schema::hasColumn($tblReturnLines, 'restock'), fn($q) => $q->where('rl.restock', 1));

                if (Schema::hasColumn($tblReturnLines, 'fabric_id')) {
                    if ($fabricId !== null) $resQ->where('rl.fabric_id', $fabricId);
                    else                    $resQ->whereNull('rl.fabric_id');
                }
                if (Schema::hasColumn($tblReturnLines, 'color_id')) {
                    if ($colorId !== null)  $resQ->where('rl.color_id', $colorId);
                    else                    $resQ->whereNull('rl.color_id');
                }

                // escludi prenotazioni del mio OC (edit)
                $hasOrderId    = Schema::hasColumn($tblResReservations, 'order_id');
                $hasOrderItem  = Schema::hasColumn($tblResReservations, 'order_item_id');
                if ($excludeOrderId && ($hasOrderId || ($hasOrderItem && $tblOrderItems && Schema::hasColumn($tblOrderItems, 'order_id')))) {
                    if ($hasOrderId) {
                        $resQ->where('rr.order_id', '!=', $excludeOrderId);
                    } else {
                        $resQ->join("$tblOrderItems as oi", 'oi.id', '=', 'rr.order_item_id')
                            ->where('oi.order_id', '!=', $excludeOrderId);
                    }
                }

                $reservedOnLines = (float) $resQ->selectRaw('COALESCE(SUM(rr.quantity),0) as qty')->value('qty');
            }

            // se esiste una colonna rl.reserved_qty usa il massimo (prudenza)
            if (Schema::hasColumn($tblReturnLines, 'reserved_qty')) {
                $resvCol = (float) $base->clone()->selectRaw('COALESCE(SUM(rl.reserved_qty),0) as qty')->value('qty');
                $reservedOnLines = max($reservedOnLines, $resvCol);
            }

            $total += max(0.0, $fromLines - $reservedOnLines);
        }

        return $total > 0 ? $total : 0.0;
    }

    /**
     * Libera (in tutto o in parte) una prenotazione di prodotti finiti per un OC.
     * - Cerca righe product_stock_levels riservate (reserved_for = order_id).
     * - LIFO: libera per prime le prenotazioni più recenti.
     * - Se necessario, splitta la riga (quando qty riga > qty da liberare e NON esiste reserved_qty).
     *
     * @param int $orderId ID dell’ordine cliente per cui era stata fatta la prenotazione
     * @param int $productId ID del prodotto
     * @param int|null $fabricId ID della variante fabric, se applicabile
     * @param int|null $colorId ID della variante color, se applicabile
     * @param float $quantity Quantità da liberare (≥0)
     * @return array{ released: float, leftover: float } Quantità effettivamente liberata e quantità non trovata
     */
    public function releaseForProduct(
        int $orderId,
        int $productId,
        ?int $fabricId,
        ?int $colorId,
        float $quantity
    ): array {
        $toFree = max(0.0, (float)$quantity);
        if ($toFree <= 0) {
            return ['released' => 0.0, 'leftover' => 0.0];
        }

        $q = \DB::table('product_stock_levels')
            ->where('reserved_for', $orderId)
            ->where('product_id', $productId);

        if ($fabricId !== null) $q->where('fabric_id', $fabricId);
        else                    $q->whereNull('fabric_id');

        if ($colorId  !== null) $q->where('color_id',  $colorId);
        else                    $q->whereNull('color_id');

        // LIFO: libera per prime le prenotazioni più recenti
        $rows = $q->orderByDesc('id')->lockForUpdate()->get();

        $released = 0.0;

        foreach ($rows as $row) {
            if ($toFree <= 0) break;

            $take = min((float)$row->quantity, $toFree);

            // riduci la riga o "stacca" la prenotazione (reserved_for -> NULL)
            if ($take >= (float)$row->quantity) {
                // liberiamo tutta la riga: la lasciamo disponibile a magazzino resi
                \DB::table('product_stock_levels')
                    ->where('id', $row->id)
                    ->update(['reserved_for' => null]);
            } else {
                // split logico: diminuisci la quantità della riga riservata e crea (se serve) una riga libera
                \DB::table('product_stock_levels')
                    ->where('id', $row->id)
                    ->update(['quantity' => (float)$row->quantity - $take]);

                \DB::table('product_stock_levels')->insert([
                    'order_id'     => $row->order_id,     // mantiene orine di origine del reso se serve
                    'warehouse_id' => $row->warehouse_id,
                    'product_id'   => $row->product_id,
                    'fabric_id'    => $row->fabric_id,
                    'color_id'     => $row->color_id,
                    'quantity'     => $take,
                    'reserved_for' => null,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $released += $take;
            $toFree   -= $take;
        }

        return [
            'released' => (float)$released,
            'leftover' => max(0.0, (float)$toFree),
        ];
    }

}
