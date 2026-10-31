<?php

namespace Tests\Feature;

use App\Http\Controllers\OrderPublicConfirmationController;
use App\Livewire\OrderRejectionNotifier;
use App\Mail\Orders\OrderConfirmationOutcomeMail;
use App\Models\Alert;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderRejectionNotificationTest extends TestCase
{
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        /** Interrompe i test prima di qualsiasi scrittura se il database non è quello isolato in memoria. */
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['activitylog.enabled' => false]);
        Mail::fake();

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2025_06_14_080152_add_two_factor_columns_to_users_table.php',
            '2025_06_14_101737_create_permission_tables.php',
            '2025_06_14_123747_create_alerts_table.php',
            '2026_07_14_120115_add_dedupe_key_to_alerts_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('email')->nullable();
        });
        Schema::create('order_numbers', function (Blueprint $table) {
            $table->id();
            $table->integer('number');
            $table->string('order_type');
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('order_number_id')->nullable();
            $table->unsignedBigInteger('occasional_customer_id')->nullable();
            $table->boolean('status')->default(false);
            $table->text('reason')->nullable();
            $table->uuid('confirm_token')->nullable()->unique();
            $table->timestamp('confirmation_requested_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->date('delivery_date')->nullable();
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 12, 2);
        });
        (require database_path('migrations/2025_07_22_214125_create_order_item_phase_events_table.php'))->up();
        (require database_path('migrations/2025_07_22_223446_create_view_order_item_phase_qty.php'))->up();

        foreach (['alerts.view', 'orders.customer.view', 'orders.customer.update'] as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }
        $this->operator = User::factory()->create();
        $this->operator->givePermissionTo(['alerts.view', 'orders.customer.view', 'orders.customer.update']);
        Role::create(['name' => 'Commerciale', 'guard_name' => 'web']);
        User::factory()->create()->assignRole('Commerciale');
        $this->withoutVite();
    }

    public function test_rejection_saves_the_alert_and_sends_the_existing_emails_without_storing_the_token(): void
    {
        $order = $this->createOrder();
        $token = $order->confirm_token;

        $this->postJson(route('orders.customer.confirm.reject', $token), ['reason' => 'Misure da correggere'])
            ->assertOk()->assertJsonPath('ok', true);

        $order->refresh();
        $this->assertFalse($order->status);
        $this->assertSame('Misure da correggere', $order->reason);
        $this->assertNull($order->confirm_token);
        $this->assertNull($order->confirmation_requested_at);
        $alert = Alert::sole();
        $this->assertSame('order_rejected', $alert->type);
        $this->assertSame($order->id, $alert->payload['order_id']);
        $this->assertSame(123, $alert->payload['order_number']);
        $this->assertStringContainsString('Cliente di prova', $alert->message);
        $this->assertStringContainsString('Misure da correggere', $alert->message);
        $this->assertStringNotContainsString($token, json_encode($alert->getAttributes()));
        $this->assertFalse($alert->is_read);
        Mail::assertSent(OrderConfirmationOutcomeMail::class, 2);
        Mail::assertNotSent(OrderConfirmationOutcomeMail::class, fn ($mail) => $mail->accepted);
    }

    public function test_reusing_a_rejected_link_does_not_duplicate_alerts_or_emails(): void
    {
        $order = $this->createOrder();
        $url = route('orders.customer.confirm.reject', $order->confirm_token);

        $this->postJson($url, ['reason' => 'Primo rifiuto'])->assertOk();
        $this->postJson($url, ['reason' => 'Secondo invio'])->assertGone();

        $this->assertDatabaseCount('alerts', 1);
        $this->assertSame('Primo rifiuto', $order->fresh()->reason);
        Mail::assertSent(OrderConfirmationOutcomeMail::class, 2);
    }

    public function test_a_new_confirmation_request_can_generate_a_second_rejection_for_the_same_order(): void
    {
        $order = $this->createOrder();
        $this->postJson(route('orders.customer.confirm.reject', $order->confirm_token), ['reason' => 'Primo rifiuto'])->assertOk();

        $newToken = (string) Str::uuid();
        DB::table('orders')->where('id', $order->id)->update([
            'confirm_token' => $newToken,
            'confirmation_requested_at' => now(),
            'reason' => null,
        ]);
        $this->postJson(route('orders.customer.confirm.reject', $newToken), ['reason' => 'Nuove misure errate'])->assertOk();

        $this->assertDatabaseCount('alerts', 2);
        $this->assertSame(2, Alert::query()->distinct()->count('dedupe_key'));
        $this->assertSame('Nuove misure errate', $order->fresh()->reason);
    }

    public function test_invalid_reasons_leave_the_order_and_token_unchanged(): void
    {
        $order = $this->createOrder();
        $url = route('orders.customer.confirm.reject', $order->confirm_token);
        foreach ([[], ['reason' => '   '], ['reason' => str_repeat('x', 1001)]] as $data) {
            $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('reason');
        }

        $this->assertSame($order->confirm_token, $order->fresh()->confirm_token);
        $this->assertNull($order->fresh()->reason);
        $this->assertDatabaseCount('alerts', 0);
        Mail::assertNothingSent();
    }

    public function test_invalid_expired_and_occasional_customer_links_cannot_generate_alerts(): void
    {
        $expired = $this->createOrder(['confirmation_requested_at' => now()->subDays(15)]);
        $occasional = $this->createOrder(['occasional_customer_id' => 10]);

        foreach ([(string) Str::uuid(), $expired->confirm_token, $occasional->confirm_token] as $token) {
            $this->postJson(route('orders.customer.confirm.reject', $token), ['reason' => 'Rifiuto'])->assertGone();
        }
        $this->post(route('orders.customer.confirm.reject', $expired->confirm_token), ['reason' => 'Rifiuto'])->assertGone();
        $this->assertDatabaseCount('alerts', 0);
        Mail::assertNothingSent();
    }

    public function test_confirmation_consumes_the_link_and_prevents_later_rejection(): void
    {
        $order = $this->createOrder();
        $this->postJson(route('orders.customer.confirm.accept', $order->confirm_token))->assertOk();
        $this->postJson(route('orders.customer.confirm.reject', $order->confirm_token), ['reason' => 'Troppo tardi'])->assertGone();

        $this->assertTrue($order->fresh()->status);
        $this->assertNull($order->fresh()->reason);
        $this->assertDatabaseCount('alerts', 0);
        Mail::assertSent(OrderConfirmationOutcomeMail::class, 2);
    }

    public function test_rejection_consumes_the_link_and_prevents_later_confirmation(): void
    {
        $order = $this->createOrder();
        $this->postJson(route('orders.customer.confirm.reject', $order->confirm_token), ['reason' => 'Rifiutato'])->assertOk();
        $this->postJson(route('orders.customer.confirm.accept', $order->confirm_token))->assertGone();

        $this->assertFalse($order->fresh()->status);
        $this->assertSame('Rifiutato', $order->fresh()->reason);
        $this->assertDatabaseCount('alerts', 1);
        Mail::assertSent(OrderConfirmationOutcomeMail::class, 2);
    }

    public function test_rejection_does_not_overwrite_a_confirmation_that_won_after_the_initial_lookup(): void
    {
        $stale = $this->createOrder();
        DB::table('orders')->where('id', $stale->id)->update([
            'status' => true,
            'confirmed_at' => now(),
            'confirm_token' => null,
            'confirmation_requested_at' => null,
        ]);
        $this->useStaleInitialLookup($stale);

        $this->postJson(route('orders.customer.confirm.reject', $stale->confirm_token), ['reason' => 'Richiesta concorrente'])->assertGone();

        $this->assertTrue($stale->fresh()->status);
        $this->assertNull($stale->fresh()->reason);
        $this->assertDatabaseCount('alerts', 0);
        Mail::assertNothingSent();
    }

    public function test_confirmation_does_not_overwrite_a_rejection_that_won_after_the_initial_lookup(): void
    {
        $stale = $this->createOrder();
        DB::table('orders')->where('id', $stale->id)->update([
            'reason' => 'Rifiuto già registrato',
            'confirm_token' => null,
            'confirmation_requested_at' => null,
        ]);
        $this->useStaleInitialLookup($stale);

        $this->postJson(route('orders.customer.confirm.accept', $stale->confirm_token))->assertGone();

        $this->assertFalse($stale->fresh()->status);
        $this->assertSame('Rifiuto già registrato', $stale->fresh()->reason);
        Mail::assertNothingSent();
    }

    public function test_alert_failure_rolls_back_the_rejection_and_token_consumption(): void
    {
        $order = $this->createOrder();
        Alert::creating(fn () => throw new \RuntimeException('Errore simulato nel salvataggio alert'));
        $this->withoutExceptionHandling();

        try {
            $this->postJson(route('orders.customer.confirm.reject', $order->confirm_token), ['reason' => 'Rifiuto']);
            $this->fail('Il salvataggio avrebbe dovuto fallire.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Errore simulato nel salvataggio alert', $exception->getMessage());
        } finally {
            Alert::flushEventListeners();
        }

        $this->assertSame($order->confirm_token, $order->fresh()->confirm_token);
        $this->assertNull($order->fresh()->reason);
        $this->assertDatabaseCount('alerts', 0);
        Mail::assertNothingSent();
    }

    public function test_email_failure_does_not_lose_the_saved_alert(): void
    {
        $order = $this->createOrder();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Invio non disponibile'));

        $this->postJson(route('orders.customer.confirm.reject', $order->confirm_token), ['reason' => 'Rifiuto'])->assertOk();

        $this->assertSame('Rifiuto', $order->fresh()->reason);
        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_polling_displays_new_rejections_and_escapes_the_customer_reason(): void
    {
        $component = Livewire::actingAs($this->operator)->test(OrderRejectionNotifier::class);
        $component->assertSeeHtml('wire:poll.10s="refreshAlerts"');
        $order = $this->createOrder();
        $reason = '<b>Misure da correggere</b>';

        $this->postJson(route('orders.customer.confirm.reject', $order->confirm_token), ['reason' => $reason])->assertOk();
        $component->call('refreshAlerts')->assertSee('Ordine rifiutato')->assertSee($reason)->assertDontSeeHtml($reason);

        $this->get(route('alerts.index'))->assertOk()->assertSee('Ordine rifiutato')
            ->assertSee('Non applicabile')->assertSee($reason)->assertDontSee($reason, false);
    }

    public function test_closing_a_notice_persists_across_navigation_without_marking_the_shared_alert_read(): void
    {
        $component = Livewire::actingAs($this->operator)->test(OrderRejectionNotifier::class);
        $alert = $this->createAlert('Avviso da chiudere');

        $component->call('refreshAlerts')->assertSee($alert->message)
            ->call('dismiss', $alert->id)->assertDontSee($alert->message)
            ->call('refreshAlerts')->assertDontSee($alert->message);
        Livewire::test(OrderRejectionNotifier::class)->assertDontSee($alert->message);
        $this->assertFalse($alert->fresh()->is_read);
        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_reading_or_dismissing_an_alert_does_not_hide_it_from_another_operator(): void
    {
        $other = User::factory()->create();
        $other->givePermissionTo('alerts.view');
        $first = Livewire::actingAs($this->operator)->test(OrderRejectionNotifier::class);
        $second = Livewire::actingAs($other)->test(OrderRejectionNotifier::class);
        $alert = $this->createAlert('Visibile ad entrambi');
        $alert->update(['is_read' => true]);

        Livewire::actingAs($this->operator);
        $first->call('refreshAlerts')->assertSee($alert->message)->call('dismiss', $alert->id);
        Livewire::actingAs($other);
        $second->call('refreshAlerts')->assertSee($alert->message);
    }

    public function test_initial_opening_does_not_replay_historical_alerts_or_delivery_alerts(): void
    {
        $old = $this->createAlert('Rifiuto precedente alla sessione');
        $component = Livewire::actingAs($this->operator)->test(OrderRejectionNotifier::class);
        $delivery = $this->createAlert('Promemoria consegna', ['type' => 'delivery_15_days']);
        $new = $this->createAlert('Nuovo rifiuto');

        $component->call('refreshAlerts')->assertDontSee($old->message)->assertDontSee($delivery->message)->assertSee($new->message);
    }

    public function test_a_later_visible_lower_id_is_not_lost_between_polls(): void
    {
        $component = Livewire::actingAs($this->operator)->test(OrderRejectionNotifier::class);
        $first = $this->createAlert('Prima transazione completata', ['id' => 20]);
        $component->call('refreshAlerts')->assertSee($first->message);
        $late = $this->createAlert('Transazione completata dopo', ['id' => 19]);

        $component->call('refreshAlerts')->assertSee($first->message)->assertSee($late->message);
    }

    public function test_notifications_waiting_beyond_the_visible_limit_appear_when_space_is_freed(): void
    {
        $component = Livewire::actingAs($this->operator)->test(OrderRejectionNotifier::class);
        $alerts = collect(range(1, 6))->map(fn ($number) => $this->createAlert('Rifiuto di prova '.$number));

        $component->call('refreshAlerts')->assertSee($alerts->first()->message)->assertDontSee($alerts->last()->message)
            ->call('dismiss', $alerts->first()->id)->assertSee($alerts->last()->message);
    }

    public function test_guests_and_users_without_the_alert_permission_cannot_mount_the_notifier(): void
    {
        Livewire::test(OrderRejectionNotifier::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(OrderRejectionNotifier::class)->assertForbidden();
    }

    public function test_permission_is_checked_again_on_periodic_requests(): void
    {
        $component = Livewire::actingAs($this->operator)->test(OrderRejectionNotifier::class);
        $this->operator->revokePermissionTo('alerts.view');

        $component->call('refreshAlerts')->assertForbidden();
    }

    public function test_opening_the_notice_redirects_to_the_existing_order_link(): void
    {
        $component = Livewire::actingAs($this->operator)->test(OrderRejectionNotifier::class);
        $order = $this->createOrder();
        $alert = $this->createAlert('Ordine da aprire', ['payload' => ['order_id' => $order->id]]);

        $component->call('refreshAlerts')->call('openAlert', $alert->id)
            ->assertRedirectToRoute('alerts.show', ['alert' => $alert->id]);
        $this->get(route('alerts.show', $alert))->assertRedirect(route('orders.customer.index', ['open_order' => $order->id]));
        $this->assertTrue($alert->fresh()->is_read);
        Livewire::test(OrderRejectionNotifier::class)->assertDontSee($alert->message);
    }

    public function test_opening_an_order_requires_its_permission_even_with_alert_access(): void
    {
        $this->operator->revokePermissionTo('orders.customer.view');
        $component = Livewire::actingAs($this->operator)->test(OrderRejectionNotifier::class);
        $alert = $this->createAlert('Avviso senza permesso ordine');

        $component->call('refreshAlerts')->assertSee('Apri Alert')->assertDontSee('Apri ordine')
            ->call('openAlert', $alert->id)->assertForbidden();
        $this->assertFalse($alert->fresh()->is_read);
    }

    public function test_page_snapshot_supports_real_livewire_poll_dismiss_and_open_requests(): void
    {
        $this->actingAs($this->operator);
        $html = $this->get(route('alerts.index'))->assertOk()->getContent();
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        $snapshot = null;
        foreach ($document->getElementsByTagName('div') as $element) {
            $candidate = $element->getAttribute('wire:snapshot');
            if ($candidate && data_get(json_decode($candidate, true), 'memo.name') === 'order-rejection-notifier') {
                $snapshot = $candidate;
                break;
            }
        }
        $this->assertNotNull($snapshot);
        $order = $this->createOrder();
        $message = "L'ordine #123 per Cliente di prova è stato rifiutato. Motivo: verificare le misure e concordare una nuova consegna con il cliente.";
        $alert = $this->createAlert($message, ['payload' => ['order_id' => $order->id]]);
        $update = function (string $currentSnapshot, string $method, array $params = []) {
            return $this->postJson('/livewire/update', [
                'components' => [[
                    'snapshot' => $currentSnapshot,
                    'updates' => (object) [],
                    'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
                ]],
            ], ['X-Livewire' => 'true'])->assertOk()->json();
        };

        $poll = $update($snapshot, 'refreshAlerts');
        $this->assertStringContainsString(e($message), $poll['components'][0]['effects']['html']);
        $dismiss = $update($poll['components'][0]['snapshot'], 'dismiss', [$alert->id]);
        $this->assertStringNotContainsString(e($message), $dismiss['components'][0]['effects']['html']);
        $afterDismiss = $update($dismiss['components'][0]['snapshot'], 'refreshAlerts');
        $this->assertStringNotContainsString(e($message), $afterDismiss['components'][0]['effects']['html']);
        $open = $update($poll['components'][0]['snapshot'], 'openAlert', [$alert->id]);
        $this->assertSame(route('alerts.show', $alert), $open['components'][0]['effects']['redirect']);

        /** L'esportazione facoltativa serve soltanto al browser isolato: non espone endpoint né modifica il database reale. */
        if (getenv('REJECTION_BROWSER_FIXTURES') === '1') {
            $directory = storage_path('framework/testing/order-rejection-browser');
            if (!is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            file_put_contents($directory.'/page.html', $html);
            file_put_contents($directory.'/responses.json', json_encode(compact('poll', 'dismiss', 'afterDismiss', 'open'), JSON_THROW_ON_ERROR));
        }
    }

    #[DataProvider('manualConfirmationFailures')]
    public function test_manual_confirmation_does_not_log_tokens_even_inside_exception_messages(bool $throw): void
    {
        $order = $this->createOrder();
        $token = $order->confirm_token;
        $this->app->instance(OrderPublicConfirmationController::class, new class($throw) extends OrderPublicConfirmationController {
            public function __construct(private bool $throw) {}

            public function confirm(Request $request, string $token)
            {
                if ($this->throw) {
                    throw new \RuntimeException('Errore simulato contenente '.$token);
                }

                return response()->json(['ok' => false], 410);
            }
        });
        Log::spy();

        $this->actingAs($this->operator)->post(route('orders.customer.confirm.manual', $order))->assertRedirect();

        Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) use ($token) {
            return str_starts_with($message, 'confirmManual:')
                && !array_key_exists('token', $context)
                && !str_contains(json_encode($context), $token);
        });
        $this->assertTrue($order->fresh()->status);
    }

    public static function manualConfirmationFailures(): array
    {
        return ['esito negativo' => [false], 'eccezione' => [true]];
    }

    private function createOrder(array $attributes = []): Order
    {
        $customerId = DB::table('customers')->insertGetId(['company' => 'Cliente di prova', 'email' => 'cliente@example.test']);
        $numberId = DB::table('order_numbers')->insertGetId(['number' => 123, 'order_type' => 'customer']);
        $id = DB::table('orders')->insertGetId(array_replace([
            'customer_id' => $customerId,
            'order_number_id' => $numberId,
            'status' => false,
            'confirm_token' => (string) Str::uuid(),
            'confirmation_requested_at' => now(),
            'delivery_date' => now()->addDays(40)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return Order::findOrFail($id);
    }

    private function createAlert(string $message, array $attributes = []): Alert
    {
        return Alert::unguarded(fn () => Alert::create(array_replace([
            'type' => 'order_rejected',
            'message' => $message,
            'is_read' => false,
            'triggered_at' => now(),
        ], $attributes)));
    }

    private function useStaleInitialLookup(Order $snapshot): void
    {
        /** Simula una richiesta già in corso: il secondo controllo deve leggere lo stato corrente nel database. */
        $this->app->instance(OrderPublicConfirmationController::class, new class($snapshot) extends OrderPublicConfirmationController {
            public function __construct(private Order $snapshot) {}

            protected function findValidByToken(string $token, bool $lock = false): ?Order
            {
                return $lock ? parent::findValidByToken($token, true) : clone $this->snapshot;
            }
        });
    }
}
