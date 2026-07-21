<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use App\Models\Product;
use App\Models\Fabric;
use App\Models\Color;
use App\Models\Component;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Customer;
use App\Services\TessuComponentResolver;
use App\Exceptions\BusinessRuleException;
use App\Services\InventoryService;

class TessuResolutionTest extends TestCase
{
    use \Tests\Traits\CreatesTessuSchema;

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
        
        \Illuminate\Support\Facades\Cache::flush();
        $this->createTessuSchema();
        
        $this->resolver = app(TessuComponentResolver::class);

        $this->category = \App\Models\ComponentCategory::create(['code' => 'TESSU', 'name' => 'Tessuto', 'type' => 'raw_material']);
        
        $this->fabric = Fabric::create(['code' => 'F1', 'name' => 'Tessuto 1', 'active' => true]);
        $this->color = Color::create(['code' => 'C1', 'name' => 'Colore 1', 'active' => true]);
        
        $this->placeholder = Component::create([
            'category_id' => $this->category->id,
            'code' => 'TESSU-00001',
            'description' => 'TESSU Placeholder',
            'is_active' => true,
        ]);
        
        $this->realComponent = Component::create([
            'category_id' => $this->category->id,
            'code' => 'TESSU-REAL',
            'description' => 'Real Component',
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
            'variable_slot' => 'TESSU',
            'is_variable' => 1
        ]);
    }

    public function test_resolver_excludes_bom_placeholder_from_valid_components()
    {
        try {
            $resolved = $this->resolver->resolveForNewLine($this->product, null, null);
            if ($resolved !== null) {
                $this->assertNotEquals($this->placeholder->id, $resolved->id, "Il resolver ha restituito il placeholder come componente reale!");
            } else {
                $this->assertNull($resolved);
            }
        } catch (ValidationException $e) {
            $this->assertTrue(true);
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
            'unit_price' => 100,
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
        
        // Pass array of orderLines with an invalid resolved_component_id
        $inventoryService->explodeBom([
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'resolved_component_id' => 999999
            ]
        ]);
    }
    
    public function test_resolver_throws_business_rule_exception_based_on_context()
    {
        $customer = Customer::create(['name' => 'Test']);
        $order = Order::create(['customer_id' => $customer->id, 'status' => 'new']);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'status' => 'cucito'
        ]);
        $item->variable()->create([
            'fabric_id' => 999, 
            'color_id' => 999, 
            'resolved_component_id' => 999 
        ]);
        
        $this->expectException(BusinessRuleException::class);
        $this->resolver->resolveForStoredLine($this->product, null, 999, 999);
    }
}
