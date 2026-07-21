<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ValidatedOrderUpdateService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ValidatedOrderUpdateServiceTest extends TestCase
{
    /**
     * Verifica che una riga esistente non possa essere riutilizzata
     * per salvare dati calcolati su un prodotto differente.
     */
    public function test_it_rejects_product_changes_on_an_existing_order_item(): void
    {
        $order = new Order();
        $order->id = 10;

        $item = new OrderItem();
        $item->id = 25;
        $item->order_id = 10;
        $item->product_id = 100;

        // La relazione già caricata evita qualsiasi accesso al database nel test unitario.
        $order->setRelation('items', new Collection([$item]));

        $payload = collect([
            [
                'key' => 'historical:25',
                'order_item_id' => 25,
                'product_id' => 200,
                'quantity' => 1,
                'price' => '100.00',
            ],
        ]);

        try {
            app(ValidatedOrderUpdateService::class)->handle($order, $payload);
            $this->fail('Il cambio prodotto sulla stessa riga doveva essere respinto.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'lines.0.product_id' => [
                    'Il prodotto di una riga esistente non può essere modificato. Elimina la riga e aggiungine una nuova.',
                ],
            ], $exception->errors());
        }
    }
}
