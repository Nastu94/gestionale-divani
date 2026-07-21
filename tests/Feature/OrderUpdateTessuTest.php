<?php

namespace Tests\Feature;

use App\Models\Color;
use App\Models\Component;
use App\Models\Customer;
use App\Models\Fabric;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderUpdateService;
use Tests\TestCase;

class OrderUpdateTessuTest extends TestCase
{
    use \Tests\Traits\CreatesTessuSchema;

    protected Product $product;
    protected Fabric $fabric1;
    protected Color $color1;
    protected Fabric $fabric2;
    protected Color $color2;
    protected Component $placeholder;
    protected Component $comp1;
    protected Component $comp2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTessuSchema();

        $category = \App\Models\ComponentCategory::create([
            'code' => 'TESSU',
            'name' => 'Tessuto',
            'type' => 'raw_material',
        ]);

        $this->fabric1 = Fabric::create([
            'code' => 'F1',
            'name' => 'Tessuto 1',
            'active' => true,
        ]);
        $this->color1 = Color::create([
            'code' => 'C1',
            'name' => 'Colore 1',
            'active' => true,
        ]);

        $this->fabric2 = Fabric::create([
            'code' => 'F2',
            'name' => 'Tessuto 2',
            'active' => true,
        ]);
        $this->color2 = Color::create([
            'code' => 'C2',
            'name' => 'Colore 2',
            'active' => true,
        ]);

        $this->placeholder = Component::create([
            'category_id' => $category->id,
            'code' => 'TESSU-00001',
            'description' => 'Slot TESSU',
            'is_active' => true,
        ]);

        $this->comp1 = Component::create([
            'category_id' => $category->id,
            'code' => 'TESSU-REAL1',
            'description' => 'TESSU reale 1',
            'fabric_id' => $this->fabric1->id,
            'color_id' => $this->color1->id,
            'is_active' => true,
        ]);

        $this->comp2 = Component::create([
            'category_id' => $category->id,
            'code' => 'TESSU-REAL2',
            'description' => 'TESSU reale 2',
            'fabric_id' => $this->fabric2->id,
            'color_id' => $this->color2->id,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Divano Test',
            'base_price' => 100,
        ]);

        $this->product->components()->attach($this->placeholder->id, [
            'quantity' => 10,
            'is_variable' => true,
            'variable_slot' => 'TESSU',
        ]);
    }

    /**
     * Verifica che l'aggiornamento conservi la stessa riga ordine
     * e sostituisca correttamente la configurazione TESSU selezionata.
     */
    public function test_order_update_service_updates_tessu_identity_when_fabric_changes(): void
    {
        $customer = Customer::create(['name' => 'Test Customer']);
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_date' => now(),
            'status' => 0,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 100,
            'status' => 'da_pianificare',
        ]);

        $item->variable()->create([
            'fabric_id' => $this->fabric1->id,
            'color_id' => $this->color1->id,
            'resolved_component_id' => $this->comp1->id,
        ]);

        $payload = collect([
            [
                'key' => 'historical:' . $item->id,
                'order_item_id' => $item->id,
                'product_id' => $this->product->id,
                'quantity' => 5,
                'price' => '100.00',
                'fabric_id' => $this->fabric2->id,
                'color_id' => $this->color2->id,
                'resolved_component_id' => $this->comp2->id,
            ],
        ]);

        app(OrderUpdateService::class)->handle(
            $order,
            $payload,
            now()->addDays(10)->toDateString(),
            null
        );

        $item->refresh()->load('variable');

        $this->assertSame($this->product->id, (int) $item->product_id);
        $this->assertSame($this->fabric2->id, (int) $item->variable->fabric_id);
        $this->assertSame($this->color2->id, (int) $item->variable->color_id);
        $this->assertSame($this->comp2->id, (int) $item->variable->resolved_component_id);
    }
}
