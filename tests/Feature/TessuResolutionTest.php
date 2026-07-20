<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use App\Models\Product;
use App\Models\Fabric;
use App\Models\Color;
use App\Models\Component;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Customer;
use App\Services\TessuComponentResolver;
use App\Exceptions\BusinessRuleException;
use App\Services\InventoryService;

class TessuResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected TessuComponentResolver $resolver;
    protected $product;
    protected $fabric;
    protected $color;
    protected $category;
    protected $placeholder;
    protected $realComponent;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->resolver = app(TessuComponentResolver::class);

        $this->category = Category::create(['name' => 'Tessuto', 'type' => 'raw_material']);
        
        $this->fabric = Fabric::create(['code' => 'F1', 'name' => 'Tessuto 1', 'active' => true]);
        $this->color = Color::create(['code' => 'C1', 'name' => 'Colore 1', 'active' => true]);
        
        $this->placeholder = Component::create([
            'category_id' => $this->category->id,
            'name' => 'TESSU-00001',
            'is_active' => true,
        ]);
        
        $this->realComponent = Component::create([
            'category_id' => $this->category->id,
            'name' => 'TESSU-REAL',
            'fabric_id' => $this->fabric->id,
            'color_id' => $this->color->id,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Divano Test',
            'base_price' => 100,
        ]);
        
        $this->product->components()->attach($this->placeholder->id, [
            'quantity' => 10,
            'variable_slot' => 'TESSU'
        ]);
    }

    public function test_resolver_excludes_bom_placeholder_from_valid_components()
    {
        $resolved = $this->resolver->resolveForNewLine($this->product, null, null);
        
        if ($resolved !== null) {
            $this->assertNotEquals($this->placeholder->id, $resolved->id, "Il resolver ha restituito il placeholder come componente reale!");
        } else {
            $this->assertNull($resolved);
        }
    }
    
    public function test_resolver_handles_historical_line_with_null_resolved_id()
    {
        $customer = Customer::create(['name' => 'Test Customer']);
        $order = Order::create(['customer_id' => $customer->id, 'order_date' => now(), 'status' => 'new']);
        
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100,
            'status' => 'cucito'
        ]);
        
        $item->variable()->create([
            'fabric_id' => $this->fabric->id,
            'color_id' => $this->color->id,
            'resolved_component_id' => null 
        ]);
        
        $resolved = $this->resolver->resolveForStoredLine($this->product, null, $this->fabric->id, $this->color->id);
        
        $this->assertNotNull($resolved);
        $this->assertEquals($this->realComponent->id, $resolved->id);
    }

    public function test_inventory_service_rejects_invalid_resolved_component_id()
    {
        $inventoryService = app(InventoryService::class);
        
        $this->expectException(BusinessRuleException::class);
        
        $inventoryService->explodeBom($this->product, 1, $this->placeholder->id);
    }
    
    public function test_resolver_throws_business_rule_exception_based_on_context()
    {
        $action = app(\App\Actions\AdvanceOrderItemPhaseAction::class);
        
        $customer = Customer::create(['name' => 'Test']);
        $order = Order::create(['customer_id' => $customer->id, 'status' => 'new']);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100,
            'status' => 'cucito'
        ]);
        $item->variable()->create([
            'fabric_id' => 999, 
            'color_id' => 999, 
            'resolved_component_id' => 999 
        ]);
        
        $this->expectException(BusinessRuleException::class);
        $action->execute($item, 'assemblaggio');
    }
}
