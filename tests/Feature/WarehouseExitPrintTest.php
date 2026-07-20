<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Product;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class WarehouseExitPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'stock.exit', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    public function test_print_exits_includes_order_reference()
    {
        $customer = Customer::create(['name' => 'Test Customer', 'company' => 'Test Company']);
        
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_date' => now(),
            'status' => 'new',
            'reference' => 'MY-REF-12345'
        ]);
        
        $product = Product::create([
            'name' => 'Divano Test',
            'base_price' => 100,
            'sku' => 'DIV-001'
        ]);
        
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100,
            'status' => 'da_pianificare'
        ]);
        
        // Dobbiamo simulare che l'item abbia una quantità nella fase 1
        \DB::table('order_item_phase_events')->insert([
            'order_item_id' => $item->id,
            'from_phase' => 0,
            'to_phase' => 1,
            'quantity' => 2,
            'changed_by' => $this->user->id,
            'is_rollback' => 0
        ]);

        $token = 'test-token-123';
        
        \Cache::put("warehouse_exit_print:{$token}", collect([$item->id]), now()->addMinutes(5));

        $response = $this->actingAs($this->user)
                         ->get("/warehouse/exits/print-selected?token={$token}&phase=1");

        $response->assertStatus(200);
        $response->assertViewHas('rows');
        
        $rows = $response->original->getData()['rows'];
        $firstRow = $rows->first();
        
        $this->assertEquals('MY-REF-12345', $firstRow->reference);
        $response->assertSee('MY-REF-12345');
    }
}
