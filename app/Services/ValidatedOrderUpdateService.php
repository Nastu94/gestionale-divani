<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Protegge l'aggiornamento degli ordini da payload che tentano di
 * riutilizzare una riga esistente con un prodotto differente.
 *
 * La logica operativa rimane nel servizio padre: questa classe aggiunge
 * esclusivamente i controlli di integrità sull'identità delle righe.
 */
class ValidatedOrderUpdateService extends OrderUpdateService
{
    /**
     * Valida l'identità delle righe e delega l'aggiornamento al servizio esistente.
     *
     * @param  \App\Models\Order  $order
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $payload
     * @param  string|null  $newDate
     * @param  mixed  $actor
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order, Collection $payload, ?string $newDate = null, $actor = null): array
    {
        $order->loadMissing('items:id,order_id,product_id');

        /**
         * Indicizziamo le righe appartenenti all'ordine corrente.
         * In questo modo un ID appartenente a un altro ordine viene respinto.
         */
        $currentItems = $order->items->keyBy(
            fn ($item): int => (int) $item->getKey()
        );

        $receivedItemIds = [];

        foreach ($payload->values() as $index => $line) {
            $orderItemId = data_get($line, 'order_item_id');

            // Le righe nuove non hanno ancora un order_item_id e non richiedono questo controllo.
            if ($orderItemId === null || $orderItemId === '') {
                continue;
            }

            $orderItemId = (int) $orderItemId;
            $field = "lines.{$index}.order_item_id";

            if (isset($receivedItemIds[$orderItemId])) {
                throw ValidationException::withMessages([
                    $field => 'La stessa riga ordine è stata inviata più di una volta.',
                ]);
            }

            $receivedItemIds[$orderItemId] = true;
            $currentItem = $currentItems->get($orderItemId);

            if (! $currentItem) {
                throw ValidationException::withMessages([
                    $field => 'La riga indicata non appartiene all’ordine corrente.',
                ]);
            }

            $incomingProductId = (int) data_get($line, 'product_id');

            if ((int) $currentItem->product_id !== $incomingProductId) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => 'Il prodotto di una riga esistente non può essere modificato. Elimina la riga e aggiungine una nuova.',
                ]);
            }
        }

        return parent::handle($order, $payload, $newDate, $actor);
    }
}
