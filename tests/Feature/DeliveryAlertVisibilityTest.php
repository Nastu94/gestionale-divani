<?php

namespace Tests\Feature;

use App\Enums\ProductionPhase;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
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
            $table->date('delivery_date')->nullable();
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

    public function test_pending_pieces_column_sums_only_product_quantities_before_shipping(): void
    {
        $orderId = $this->createOrder();
        $splitItemId = $this->createItem($orderId, 6);
        $this->moveQuantity($splitItemId, 4, ProductionPhase::INSERTED, ProductionPhase::ASSEMBLY);
        $this->moveQuantity($splitItemId, 2, ProductionPhase::ASSEMBLY, ProductionPhase::FINISHING);
        $this->moveQuantity($splitItemId, 1, ProductionPhase::FINISHING, ProductionPhase::SHIPPING);
        $shippedItemId = $this->createItem($orderId, 3);
        $this->advanceTo($shippedItemId, 3, ProductionPhase::SHIPPING);
        $this->createItem($orderId, 2);
        $this->createItem($orderId, 100, null);
        $fifteenDays = $this->createDeliveryAlert($orderId);
        $twentyDays = $this->createDeliveryAlert($orderId, ['type' => 'delivery_20_days']);

        $otherOrderId = $this->createOrder();
        $this->createItem($otherOrderId, 20);
        $otherAlert = $this->createDeliveryAlert($otherOrderId);
        $unrelated = $this->createDeliveryAlert($orderId, ['type' => 'low_stock', 'payload' => null]);

        $response = $this->get(route('alerts.index'))->assertOk();
        $response->assertSee('Pezzi da completare');

        $this->assertSame('7', $this->pendingPiecesCell($response, $fifteenDays));
        $this->assertSame('7', $this->pendingPiecesCell($response, $twentyDays));
        $this->assertSame('20', $this->pendingPiecesCell($response, $otherAlert));
        $this->assertSame('Non applicabile', $this->pendingPiecesCell($response, $unrelated));
    }

    public function test_pending_pieces_column_updates_after_partial_shipping_and_rollback(): void
    {
        $orderId = $this->createOrder();
        $itemId = $this->createItem($orderId, 3);
        $alert = $this->createDeliveryAlert($orderId, ['payload' => ['order_id' => (string) $orderId]]);
        $this->advanceTo($itemId, 3, ProductionPhase::FINISHING);

        $response = $this->get(route('alerts.index'))->assertOk();
        $this->assertSame('3', $this->pendingPiecesCell($response, $alert));

        $this->moveQuantity($itemId, 2, ProductionPhase::FINISHING, ProductionPhase::SHIPPING);

        $response = $this->get(route('alerts.index'))->assertOk();
        $this->assertSame('1', $this->pendingPiecesCell($response, $alert));

        $this->moveQuantity($itemId, 0.5, ProductionPhase::SHIPPING, ProductionPhase::FINISHING);

        $response = $this->get(route('alerts.index'))->assertOk();
        $this->assertSame('1,5', $this->pendingPiecesCell($response, $alert));

        $this->moveQuantity($itemId, 1.5, ProductionPhase::FINISHING, ProductionPhase::SHIPPING);

        $this->assertSame([], $this->visibleAlertIds());
    }

    public function test_expired_delivery_messages_show_the_current_due_date_without_changing_saved_alerts(): void
    {
        config(['app.timezone' => 'Europe/Rome']);
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00', 'Europe/Rome'));
        $orderId = $this->createOrder('2026-07-31');
        $this->createItem($orderId);
        $originalMessage = "ALERT CONSEGNA: L'ordine #100 per Cliente di prova scade tra 15 giorni (Consegna prevista: 31/07/2026).";
        $alert = $this->createDeliveryAlert($orderId, ['message' => $originalMessage]);
        $savedAttributes = $alert->fresh()->getAttributes();

        $response = $this->get(route('alerts.index'))->assertOk();

        $this->assertSame(
            "ALERT CONSEGNA: L'ordine #100 per Cliente di prova (Consegna prevista: 31/07/2026 - SCADUTA).",
            $this->alertCell($response, $alert, 1)
        );
        $this->assertSame($savedAttributes, $alert->fresh()->getAttributes());
    }

    public function test_delivery_messages_use_today_and_the_rescheduled_order_date(): void
    {
        config(['app.timezone' => 'Europe/Rome']);
        $this->travelTo(Carbon::parse('2026-09-16 12:00:00', 'Europe/Rome'));
        $orderId = $this->createOrder('2026-09-16');
        $this->createItem($orderId);
        $prefix = "ALERT CONSEGNA: L'ordine #100 per Cliente di prova ";
        $alert = $this->createDeliveryAlert($orderId, [
            'type' => 'delivery_20_days',
            'message' => $prefix.'scade tra 20 giorni (Consegna prevista: 31/07/2026).',
            'payload' => ['order_id' => (string) $orderId, 'delivery_date' => '2026-07-31'],
        ]);

        $response = $this->get(route('alerts.index'))->assertOk();
        $this->assertSame(
            $prefix.'scade oggi (Consegna prevista: 16/09/2026).',
            $this->alertCell($response, $alert, 1)
        );

        DB::table('orders')->where('id', $orderId)->update(['delivery_date' => '2026-09-17']);

        $response = $this->get(route('alerts.index'))->assertOk();
        $this->assertSame(
            $prefix.'scade domani (Consegna prevista: 17/09/2026).',
            $this->alertCell($response, $alert, 1)
        );

        DB::table('orders')->where('id', $orderId)->update(['delivery_date' => '2026-09-20']);

        $response = $this->get(route('alerts.index'))->assertOk();
        $this->assertSame(
            $prefix.'scade tra 4 giorni (Consegna prevista: 20/09/2026).',
            $this->alertCell($response, $alert, 1)
        );
        $this->assertSame('2026-07-31', $alert->fresh()->payload['delivery_date']);

        DB::table('orders')->where('id', $orderId)->update(['delivery_date' => null]);

        $response = $this->get(route('alerts.index'))->assertOk();
        $this->assertSame(
            $prefix.'(Consegna prevista: non indicata).',
            $this->alertCell($response, $alert, 1)
        );
    }

    public function test_delivery_becomes_expired_at_midnight_in_the_application_timezone(): void
    {
        config(['app.timezone' => 'Europe/Rome']);
        $this->travelTo(Carbon::parse('2026-09-16 21:59:59', 'UTC'));
        $orderId = $this->createOrder('2026-09-16');
        $this->createItem($orderId);
        $alert = $this->createDeliveryAlert($orderId, [
            'message' => 'Consegna di prova scade tra 15 giorni (Consegna prevista: 16/09/2026).',
        ]);

        $response = $this->get(route('alerts.index'))->assertOk();
        $this->assertSame(
            'Consegna di prova scade oggi (Consegna prevista: 16/09/2026).',
            $this->alertCell($response, $alert, 1)
        );

        $this->travelTo(Carbon::parse('2026-09-16 22:00:00', 'UTC'));

        $response = $this->get(route('alerts.index'))->assertOk();
        $this->assertSame(
            'Consegna di prova (Consegna prevista: 16/09/2026 - SCADUTA).',
            $this->alertCell($response, $alert, 1)
        );
    }

    public function test_unrelated_alert_messages_are_not_rewritten(): void
    {
        $orderId = $this->createOrder('2026-07-31');
        $message = 'Avviso generico (Consegna prevista: 31/07/2026).';
        $alert = $this->createDeliveryAlert($orderId, ['type' => 'low_stock', 'message' => $message]);

        $response = $this->get(route('alerts.index'))->assertOk();

        $this->assertSame($message, $this->alertCell($response, $alert, 1));
    }

    private function createOrder(?string $deliveryDate = null): int
    {
        return DB::table('orders')->insertGetId(['delivery_date' => $deliveryDate]);
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

    private function moveQuantity(int $itemId, int|float $quantity, ProductionPhase $from, ProductionPhase $to): void
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

    private function pendingPiecesCell(TestResponse $response, Alert $alert): string
    {
        return $this->alertCell($response, $alert, 2);
    }

    private function alertCell(TestResponse $response, Alert $alert, int $column): string
    {
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($document->loadHTML($response->getContent()));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        foreach ((new \DOMXPath($document))->query('//table/tbody/tr') as $row) {
            if ($row->getAttribute('onclick') === "window.location='".route('alerts.show', $alert)."'") {
                return trim($row->getElementsByTagName('td')->item($column)->textContent);
            }
        }

        $this->fail('Riga alert non presente nella pagina: '.$alert->id);
    }
}
