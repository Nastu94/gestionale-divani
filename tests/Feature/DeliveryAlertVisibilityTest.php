<?php

namespace Tests\Feature;

use App\Enums\ProductionPhase;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeliveryAlertVisibilityTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Questi test devono poter scrivere esclusivamente nel database in memoria.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2025_06_14_080152_add_two_factor_columns_to_users_table.php',
            '2025_06_14_101737_create_permission_tables.php',
            '2025_06_14_123747_create_alerts_table.php',
            '2026_07_14_120115_add_dedupe_key_to_alerts_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 12, 2);
        });

        (require database_path('migrations/2025_07_22_214125_create_order_item_phase_events_table.php'))->up();
        (require database_path('migrations/2025_07_22_223446_create_view_order_item_phase_qty.php'))->up();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::create([
            'name' => 'alerts.view',
            'guard_name' => 'web',
        ]));

        $this->actingAs($this->user);
        $this->withoutVite();
        $this->withoutExceptionHandling();
    }

    public function test_existing_delivery_alerts_disappear_when_all_pieces_enter_shipping(): void
    {
        $orderId = $this->createOrder();
        $itemId = $this->createItem($orderId, 2);
        $fifteenDays = $this->createDeliveryAlert($orderId);
        $twentyDays = $this->createDeliveryAlert($orderId, ['type' => 'delivery_20_days']);

        // Un altro ordine ancora in produzione non deve mantenere visibili gli alert del primo.
        $otherOrderId = $this->createOrder();
        $this->createItem($otherOrderId);
        $otherAlert = $this->createDeliveryAlert($otherOrderId);

        $this->assertEqualsCanonicalizing(
            [$fifteenDays->id, $twentyDays->id, $otherAlert->id],
            $this->visibleAlertIds()
        );

        $this->advanceTo($itemId, 2, ProductionPhase::SHIPPING);

        $this->assertSame([$otherAlert->id], $this->visibleAlertIds());
        $this->assertDatabaseCount('alerts', 3);
        $this->assertFalse($fifteenDays->fresh()->is_read);
        $this->assertFalse($twentyDays->fresh()->is_read);
    }

    public function test_partially_shipped_quantity_keeps_the_order_alert_visible(): void
    {
        $orderId = $this->createOrder();
        $itemId = $this->createItem($orderId, 3);
        $alert = $this->createDeliveryAlert($orderId);
        $this->advanceTo($itemId, 3, ProductionPhase::FINISHING);

        $this->moveQuantity($itemId, 2, ProductionPhase::FINISHING, ProductionPhase::SHIPPING);

        $this->assertSame([$alert->id], $this->visibleAlertIds());

        $this->moveQuantity($itemId, 1, ProductionPhase::FINISHING, ProductionPhase::SHIPPING);

        $this->assertSame([], $this->visibleAlertIds());
    }

    public function test_every_product_item_must_reach_shipping_before_the_alert_disappears(): void
    {
        $orderId = $this->createOrder();
        $firstItemId = $this->createItem($orderId);
        $secondItemId = $this->createItem($orderId);
        $this->createItem($orderId, 5, null);
        $alert = $this->createDeliveryAlert($orderId);

        $this->advanceTo($firstItemId, 1, ProductionPhase::SHIPPING);
        $this->advanceTo($secondItemId, 1, ProductionPhase::ASSEMBLY);

        $this->assertSame([$alert->id], $this->visibleAlertIds());

        $this->moveQuantity($secondItemId, 1, ProductionPhase::ASSEMBLY, ProductionPhase::FINISHING);
        $this->moveQuantity($secondItemId, 1, ProductionPhase::FINISHING, ProductionPhase::SHIPPING);

        $this->assertSame([], $this->visibleAlertIds());
    }

    public function test_rollback_restores_visibility_without_resetting_the_saved_alert(): void
    {
        $orderId = $this->createOrder();
        $itemId = $this->createItem($orderId);
        $alert = $this->createDeliveryAlert($orderId, [
            'is_read' => true,
            'triggered_at' => now()->subDays(3),
            'dedupe_key' => 'delivery:rollback-test',
        ]);
        $savedAttributes = $alert->fresh()->getAttributes();
        $this->advanceTo($itemId, 1, ProductionPhase::SHIPPING);

        $this->assertSame([], $this->visibleAlertIds());

        $this->moveQuantity($itemId, 1, ProductionPhase::SHIPPING, ProductionPhase::FINISHING);

        $this->assertSame([$alert->id], $this->visibleAlertIds());
        $this->assertSame($savedAttributes, $alert->fresh()->getAttributes());
    }

    public function test_filtering_happens_before_pagination_and_the_sidebar_counts_only_visible_unread_alerts(): void
    {
        for ($index = 0; $index < 21; $index++) {
            $orderId = $this->createOrder();
            $itemId = $this->createItem($orderId);
            $this->advanceTo($itemId, 1, ProductionPhase::SHIPPING);
            $this->createDeliveryAlert($orderId);
        }

        $pendingOrderId = $this->createOrder();
        $this->createItem($pendingOrderId);
        $unread = $this->createDeliveryAlert($pendingOrderId, ['triggered_at' => now()->subDay()]);
        $read = $this->createDeliveryAlert($pendingOrderId, ['is_read' => true]);
        $lowStock = Alert::create([
            'type' => 'low_stock',
            'message' => 'Avviso di giacenza di prova',
            'payload' => null,
            'is_read' => false,
            'triggered_at' => now()->subDays(2),
        ]);

        $response = $this->get(route('alerts.index'))->assertOk();
        $alerts = $response->viewData('alerts');

        $this->assertSame(3, $alerts->total());
        $this->assertSame(1, $alerts->lastPage());
        $this->assertSame([$unread->id, $lowStock->id, $read->id], $alerts->getCollection()->modelKeys());

        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($document->loadHTML($response->getContent()));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        $badges = (new \DOMXPath($document))->query(
            '//aside//a[@href="'.route('alerts.index').'"]/span[contains(@class, "bg-red-600")]'
        );
        $this->assertCount(1, $badges);
        $this->assertSame('2', trim($badges->item(0)->textContent));
    }

    public function test_unrelated_alert_types_remain_visible_for_orders_in_shipping(): void
    {
        $orderId = $this->createOrder();
        $itemId = $this->createItem($orderId);
        $this->advanceTo($itemId, 1, ProductionPhase::SHIPPING);
        $this->createDeliveryAlert($orderId);
        $unrelated = $this->createDeliveryAlert($orderId, ['type' => 'low_stock']);

        $this->assertSame([$unrelated->id], $this->visibleAlertIds());
    }

    public function test_delivery_alerts_without_remaining_product_quantities_are_not_displayed(): void
    {
        $emptyOrderId = $this->createOrder();
        $this->createDeliveryAlert($emptyOrderId);

        $componentOrderId = $this->createOrder();
        $this->createItem($componentOrderId, 5, null);
        $this->createDeliveryAlert($componentOrderId);

        $zeroQuantityOrderId = $this->createOrder();
        $this->createItem($zeroQuantityOrderId, 0);
        $this->createDeliveryAlert($zeroQuantityOrderId);

        $this->createDeliveryAlert($emptyOrderId, ['payload' => null]);

        $this->assertSame([], $this->visibleAlertIds());
    }

    public function test_order_id_stored_as_a_json_string_is_supported(): void
    {
        $orderId = $this->createOrder();
        $itemId = $this->createItem($orderId);
        $alert = $this->createDeliveryAlert($orderId, ['payload' => ['order_id' => (string) $orderId]]);

        $this->assertSame([$alert->id], $this->visibleAlertIds());

        $this->advanceTo($itemId, 1, ProductionPhase::SHIPPING);

        $this->assertSame([], $this->visibleAlertIds());
    }

    public function test_dashboard_alert_count_uses_the_same_visibility_rule(): void
    {
        $shippingOrderId = $this->createOrder();
        $itemId = $this->createItem($shippingOrderId);
        $this->advanceTo($itemId, 1, ProductionPhase::SHIPPING);
        $this->createDeliveryAlert($shippingOrderId);

        $pendingOrderId = $this->createOrder();
        $this->createItem($pendingOrderId);
        $this->createDeliveryAlert($pendingOrderId, ['is_read' => true]);

        config(['menu.dashboard_tiles' => [[
            'label' => 'Alert',
            'route' => 'alerts.index',
            'permission' => 'alerts.view',
            'icon' => 'fa-bell',
            'badge_key' => 'alerts_critical',
        ]]]);

        $view = view('components.dashboard-tiles');
        $view->render();

        $this->assertSame(1, $view->getData()['tiles'][0]['badge_count']);
    }

    private function createOrder(): int
    {
        return DB::table('orders')->insertGetId([]);
    }

    private function createItem(int $orderId, int $quantity = 1, ?int $productId = 1): int
    {
        return DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    private function createDeliveryAlert(int $orderId, array $attributes = []): Alert
    {
        return Alert::create(array_replace([
            'type' => 'delivery_15_days',
            'message' => 'Alert di consegna di prova',
            'payload' => ['order_id' => $orderId],
            'is_read' => false,
            'triggered_at' => now(),
        ], $attributes));
    }

    private function advanceTo(int $itemId, int $quantity, ProductionPhase $phase): void
    {
        for ($from = ProductionPhase::INSERTED->value; $from < $phase->value; $from++) {
            $this->moveQuantity($itemId, $quantity, ProductionPhase::from($from), ProductionPhase::from($from + 1));
        }
    }

    private function moveQuantity(int $itemId, int $quantity, ProductionPhase $from, ProductionPhase $to): void
    {
        DB::table('order_item_phase_events')->insert([
            'order_item_id' => $itemId,
            'quantity' => $quantity,
            'from_phase' => $from->value,
            'to_phase' => $to->value,
            'changed_by' => $this->user->id,
            'is_rollback' => $to->value < $from->value,
        ]);
    }

    private function visibleAlertIds(): array
    {
        return $this->get(route('alerts.index'))->assertOk()->viewData('alerts')->getCollection()->modelKeys();
    }
}
