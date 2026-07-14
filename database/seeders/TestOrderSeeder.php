<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Enums\ProductionPhase;
use Illuminate\Support\Facades\DB;

class TestOrderSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        // 1. Create a Product
        $product = Product::firstOrCreate(
            ['sku' => 'TEST-001'],
            [
                'name' => 'Divano Test 3 Posti',
                'description' => 'Generato per il test di magazzino/ddt',
                'price' => 199.99,
                'is_active' => true,
            ]
        );

        // 3. Create a Customer with Address
        $customer = Customer::create([
            'company' => 'Cliente Test Srl',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'is_active' => true,
        ]);

        $customer->addresses()->create([
            'type' => 'shipping',
            'address' => 'Via Test 123',
            'city' => 'Roma',
            'postal_code' => '00100',
            'country' => 'Italia',
        ]);

        // Create Order Number
        $orderNumber = \App\Models\OrderNumber::firstOrCreate(
            ['number' => 9999, 'order_type' => 'customer']
        );

        // 4. Create an Order
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number_id' => $orderNumber->id,
            'status' => 0, // not completed
            'delivery_date' => now()->addDays(15),
            'shipping_address' => 'Via Test 123',
            'shipping_zone' => 'Roma',
            'packages' => 5,
        ]);

        // 5. Create Order Item in phase 3 (SHIPPING)
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 199.99,
            'current_phase' => ProductionPhase::SHIPPING->value,
        ]);

        // 6. Create Phase Event to move items to phase 3
        DB::table('order_item_phase_events')->insert([
            'order_item_id' => $item->id,
            'from_phase' => 0,
            'to_phase' => ProductionPhase::SHIPPING->value,
            'quantity' => 10,
            'changed_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->command->info('Dati di test creati con successo! Puoi procedere alla spedizione (creazione DDT).');
    }
}
