<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\PoReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\CarbonImmutable;

/**
 * OrderUpdateService – versione rivista + log & safeguard product_id
 */
class OrderUpdateService
{
    protected InventoryService $inventory;
    protected ProcurementService $procurement;

    public function __construct(InventoryService $inventory, ProcurementService $procurement)
    {
        $this->inventory   = $inventory;
        $this->procurement = $procurement;
    }

    /**
     * Handle differential update of an Order Customer.
     * Logs every important step for easier debugging.
     *
     * @param Order       $order
     * @param Collection  $payload  lines [{product_id, quantity, price}]
     * @param string|null $newDate  YYYY-mm-dd or null
     * @return array{message:string, po_numbers?:array}
     */
    public function handle(Order $order, Collection $payload, ?string $newDate = null): array
    {
        Log::info('OC update – start', [
            'order_id' => $order->id,
            'payload'  => $payload,
            'new_date' => $newDate,
        ]);

        /* 1️⃣ snapshot righe DB + incoming */
        $current  = $order->items->keyBy('product_id');
        $incoming = $payload->keyBy('product_id');

        /* 2️⃣ diff */
        [$increase, $decrease] = InventoryService::diffLines($current, $incoming);

        $changedDate = $newDate && $newDate !== $order->delivery_date->format('Y-m-d');
        if ($increase->isEmpty() && $decrease->isEmpty() && ! $changedDate) {
            return ['message' => 'Nessuna modifica'];
        }

        /* 3️⃣ transazione */
        return DB::transaction(function () use ($order, $incoming, $increase, $decrease, $newDate, $changedDate) {

            /* 3.1 header */
            if ($changedDate) {
                $order->update(['delivery_date' => $newDate]);
            }

            /* 3.2 righe: delete / upsert */
            $incomingIds = $incoming->keys();
            $order->items()
                  ->whereNotIn('product_id', $incomingIds)
                  ->orWhere(function ($q) use ($incoming) {
                      foreach ($incoming as $pid => $line) {
                          if ($line['quantity'] == 0) {
                              $q->orWhere('product_id', $pid);
                          }
                      }
                  })->delete();

            foreach ($incoming as $pid => $line) {
                if ($line['quantity'] <= 0) continue;
                $order->items()->updateOrCreate(
                    ['product_id' => $pid],
                    ['quantity' => $line['quantity'], 'unit_price' => $line['price']]
                );
            }

            /* 3.3 release prenotazioni (diminuzione) */
            if ($decrease->isNotEmpty()) {
                $componentsDec = InventoryService::explodeBomArray($decrease->all());

                // 3.3.1 – libera le prenotazioni di magazzino
                $leftovers = InventoryService::forDelivery($order->delivery_date, $order->id)
                            ->releaseReservations($order, $componentsDec);

                // 3.3.2 – solo se rimane qualcosa, riduci le po_reservations
                if (!empty($leftovers)) {
                    $this->procurement->adjustAfterDecrease($order, $leftovers);
                }
            }

            /* 3.4 release prenotazioni (aumento) */
            if ($increase->isNotEmpty()) {

                $components = InventoryService::explodeBomArray($increase->all());

                // ricontrolla tenendo conto delle quantità ora libere sui PO
                $check = $this->inventory
                    ->forDelivery($newDate ?? $order->delivery_date, $order->id) // exclude OC
                    ->check(collect($increase)->map(fn($l)=>[
                        'product_id'=>$l['product_id'], 'quantity'=>$l['quantity']
                    ])->values()->all());

                if ($check->ok) {
                    // tutto coperto: NON genero nuovi PO
                    Log::info('No shortage after existing PO free qty', ['order_id'=>$order->id]);
                } else {
                    // procede come prima
                    $created = ProcurementService::fromShortage(
                        ProcurementService::buildShortageCollection($check->shortage),
                        $order->id
                    );
                    $poNumbers = $created['po_numbers']->all();
                }
            }

            /* 3.5 calcola fabbisogno ATTUALE (tutte le righe) */
            $order->load('items');                // <— aggiungi
            $usedLines = $order->items->map(fn ($l) => [
                'product_id' => $l->product_id,
                'quantity'   => $l->quantity,
            ])->values()->all();

            $inv = InventoryService::forDelivery($order->delivery_date, $order->id)
                    ->check($usedLines);

            /* 3.6 prenotazioni stock per disponibilità fisica */
            foreach ($inv->shortage as $row) {
                $needed    = $row['needed'];
                $haveAvail = $row['available'];
                $fromStock = min($haveAvail, $needed);

                if ($fromStock > 0) {
                    $sl = StockLevel::where('component_id', $row['component_id'])
                            ->orderBy('quantity')
                            ->first();

                    StockReservation::create([
                        'stock_level_id' => $sl->id,
                        'order_id'       => $order->id,
                        'quantity'       => $fromStock,
                    ]);

                    StockMovement::create([
                        'stock_level_id' => $sl->id,
                        'type'           => 'reserve',
                        'quantity'       => $fromStock,
                        'note'           => "Prenotazione stock per OC #{$order->id} (update)",
                    ]);
                }
            }

            /* 3.7 PO per eventuale shortage */
            $poNumbers = [];
            if (! $inv->ok) {
                $shortCol = ProcurementService::buildShortageCollection($inv->shortage);
                $proc     = ProcurementService::fromShortage($shortCol, $order->id);
                $poNumbers = $proc['po_numbers']->all();
            }

            /* 3.8 totale ordine */
            $total = $incoming->reduce(fn ($s, $l) => $s + $l['quantity'] * $l['price'], 0);
            $order->update(['total' => $total]);

            Log::info('OC update – end', [
                'order_id'   => $order->id,
                'total'      => $total,
                'po_numbers' => $poNumbers,
            ]);

            return ['message' => 'Ordine aggiornato', 'po_numbers' => $poNumbers];
        });
    }
}