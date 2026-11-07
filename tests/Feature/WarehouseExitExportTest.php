<?php

namespace Tests\Feature;

use App\Livewire\Warehouse\ExitTable;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Livewire\Features\SupportTesting\Testable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WarehouseExitExportTest extends TestCase
{
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        /** Ferma i test prima di qualsiasi scrittura se la connessione non è quella isolata in memoria. */
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['activitylog.enabled' => false, 'menu.dashboard_tiles' => []]);
        Mail::fake();
        $this->withoutVite();

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2025_06_14_080152_add_two_factor_columns_to_users_table.php',
            '2025_06_14_101737_create_permission_tables.php',
            '2025_06_14_123747_create_alerts_table.php',
            '2026_07_14_120115_add_dedupe_key_to_alerts_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        foreach (['customers', 'occasional_customers'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('company');
            });
        }
        Schema::create('order_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('number');
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku');
        });
        foreach (['fabrics', 'colors'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code');
            });
        }
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('occasional_customer_id')->nullable();
            $table->unsignedBigInteger('order_number_id')->nullable();
            $table->string('shipping_zone')->nullable();
            $table->string('reference')->nullable();
            $table->date('ordered_at')->nullable();
            $table->date('delivery_date')->nullable();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
        });
        Schema::create('order_product_variables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('fabric_id')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();
            $table->text('color_notes')->nullable();
        });
        (require database_path('migrations/2025_07_22_214125_create_order_item_phase_events_table.php'))->up();
        (require database_path('migrations/2025_07_22_223446_create_view_order_item_phase_qty.php'))->up();

        $this->operator = User::factory()->create();
        $this->operator->givePermissionTo(Permission::create(['name' => 'stock.exit', 'guard_name' => 'web']));
        $this->actingAs($this->operator);
    }

    public function test_excel_matches_selected_rows_in_table_order_with_phase_quantity_and_native_cell_types(): void
    {
        $first = $this->createRow('Cliente Alfa di prova', '0007');
        $second = $this->createRow('Cliente Zeta di prova', '0009');
        $this->createRow('Cliente non selezionato', '0010');
        $this->moveToShipping($second, 1);
        $before = DB::table('order_items')->get()->toJson();

        $component = Livewire::test(ExitTable::class)
            ->set('sort', 'customer')->set('dir', 'desc')
            ->set('selectedExitRowIds', [$first, $second])
            ->call('openExportModal')->assertSet('exportOpen', true)
            ->call('exportSelectedRows')->assertHasNoErrors()->assertFileDownloaded()
            ->assertSet('exportOpen', false);

        $book = $this->readDownload($component);
        $sheet = $book->getActiveSheet();
        $this->assertSame('J', $sheet->getHighestColumn());
        $this->assertSame(3, $sheet->getHighestRow());
        $this->assertSame('Cliente Zeta di prova', $sheet->getCell('A2')->getValue());
        $this->assertSame('Cliente Alfa di prova', $sheet->getCell('A3')->getValue());
        $this->assertSame('0009', $sheet->getCell('C2')->getValue());
        $this->assertSame('Divano dimostrativo', $sheet->getCell('E2')->getValue());
        $this->assertSame('Tessuto di prova / Blu / Nota dimostrativa', $sheet->getCell('F2')->getValue());
        $this->assertEquals(2, $sheet->getCell('J2')->getValue());
        $this->assertEquals(1500.75, $sheet->getCell('I2')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('J2')->getDataType());
        $this->assertSame('2026-12-16', Date::excelToDateTimeObject($sheet->getCell('H2')->getValue())->format('Y-m-d'));
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('A1:J3', $sheet->getAutoFilter()->getRange());
        $book->disconnectWorksheets();
        $this->assertSame($before, DB::table('order_items')->get()->toJson());
        Mail::assertNothingSent();
    }

    public function test_unselected_columns_are_absent_and_column_order_follows_the_table(): void
    {
        $id = $this->createRow();
        $component = Livewire::test(ExitTable::class)
            ->set('selectedExitRowIds', [$id])
            ->set('selectedExportColumns', ['qty_in_phase', 'color_note', 'customer'])
            ->assertSet('selectedExitRowIds', [$id])
            ->call('exportSelectedRows')->assertFileDownloaded();
        $book = $this->readDownload($component);
        $sheet = $book->getActiveSheet();
        $this->assertSame('C', $sheet->getHighestColumn());
        $this->assertSame(['Cliente', 'Tessuto / Colore / Nota', 'Q.ty fase'], $sheet->rangeToArray('A1:C1')[0]);
        $this->assertSame(1, $book->getSheetCount());
        foreach (['A', 'B', 'C'] as $column) {
            $this->assertTrue($sheet->getColumnDimension($column)->getVisible());
        }
        $book->disconnectWorksheets();
    }

    public function test_text_is_not_interpreted_as_excel_formulas_and_null_dates_stay_empty(): void
    {
        $id = $this->createRow('=1+1', '000123');
        DB::table('orders')->update(['reference' => '+00123', 'shipping_zone' => '@SUM(A1)', 'ordered_at' => null, 'delivery_date' => null]);
        $component = Livewire::test(ExitTable::class)->set('selectedExitRowIds', [$id])->call('exportSelectedRows');
        $book = $this->readDownload($component);
        $sheet = $book->getActiveSheet();
        foreach (['A2' => '=1+1', 'B2' => '@SUM(A1)', 'C2' => '000123', 'D2' => '+00123'] as $cell => $text) {
            $this->assertSame($text, $sheet->getCell($cell)->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($cell)->getDataType());
        }
        $this->assertEmpty($sheet->getCell('G2')->getValue());
        $this->assertEmpty($sheet->getCell('H2')->getValue());
        $book->disconnectWorksheets();
    }

    #[DataProvider('invalidColumns')]
    public function test_invalid_column_selections_do_not_download(array $columns, string $error): void
    {
        $id = $this->createRow();
        Livewire::test(ExitTable::class)->set('selectedExitRowIds', [$id])
            ->call('openExportModal')->set('selectedExportColumns', $columns)
            ->call('exportSelectedRows')->assertHasErrors($error)->assertNoFileDownloaded()
            ->assertSet('exportOpen', true);
    }

    public static function invalidColumns(): array
    {
        return [
            'empty' => [[], 'selectedExportColumns'],
            'unknown' => [['customer', 'password'], 'selectedExportColumns.1'],
            'duplicate' => [['customer', 'customer'], 'selectedExportColumns.0'],
        ];
    }

    public function test_missing_and_excessive_row_selections_do_not_download(): void
    {
        Livewire::test(ExitTable::class)->call('exportSelectedRows')
            ->assertHasErrors('selectedExitRowIds')->assertNoFileDownloaded();
        Livewire::test(ExitTable::class)->set('selectedExitRowIds', range(1, 501))
            ->call('exportSelectedRows')->assertHasErrors('selectedExitRowIds')->assertNoFileDownloaded();
    }

    public function test_rows_outside_current_page_or_filters_are_not_exported(): void
    {
        $first = $this->createRow('Alfa');
        $second = $this->createRow('Zeta');
        Livewire::test(ExitTable::class)->set('perPage', 1)
            ->set('selectedExitRowIds', [$first, $second])->call('exportSelectedRows')
            ->assertHasErrors('selectedExitRowIds')->assertNoFileDownloaded();
        Livewire::test(ExitTable::class)->set('filters.customer', 'Alfa')
            ->set('selectedExitRowIds', [$second])->call('exportSelectedRows')
            ->assertHasErrors('selectedExitRowIds')->assertNoFileDownloaded();
    }

    public function test_a_row_moved_to_another_phase_blocks_the_whole_export(): void
    {
        $first = $this->createRow();
        $second = $this->createRow();
        $component = Livewire::test(ExitTable::class)->set('selectedExitRowIds', [$first, $second])->call('openExportModal');
        $this->moveToShipping($second, 3);
        $component->call('exportSelectedRows')->assertHasErrors('selectedExitRowIds')->assertNoFileDownloaded();
    }

    public function test_changing_filters_clears_selection_and_closes_the_export_dialog(): void
    {
        $id = $this->createRow();
        Livewire::test(ExitTable::class)->set('selectedExitRowIds', [$id])->call('openExportModal')
            ->set('filters.customer', 'Alfa')->assertSet('selectedExitRowIds', [])->assertSet('exportOpen', false);
    }

    public function test_shipping_uses_occasional_customer_and_current_fractional_quantity_without_optional_variables(): void
    {
        $id = $this->createRow();
        $occasional = DB::table('occasional_customers')->insertGetId(['company' => 'Cliente occasionale di prova']);
        DB::table('orders')->update(['customer_id' => null, 'occasional_customer_id' => $occasional]);
        DB::table('order_product_variables')->where('order_item_id', $id)->delete();
        $this->moveToShipping($id, 1.25);
        $component = Livewire::test(ExitTable::class)->set('phase', 3)
            ->set('selectedExitRowIds', [$id])->set('selectedExportColumns', ['customer', 'color_note', 'qty_in_phase'])
            ->call('exportSelectedRows')->assertHasNoErrors()->assertFileDownloaded();
        $book = $this->readDownload($component);
        $sheet = $book->getActiveSheet();
        $this->assertSame('Uscite - Spedizione', $sheet->getTitle());
        $this->assertSame('Cliente occasionale di prova', $sheet->getCell('A2')->getValue());
        $this->assertEmpty($sheet->getCell('B2')->getValue());
        $this->assertSame(1.25, $sheet->getCell('C2')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_download_and_dialog_require_the_warehouse_permission(): void
    {
        $id = $this->createRow();
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(ExitTable::class)->set('selectedExitRowIds', [$id])
            ->call('exportSelectedRows')->assertForbidden()->assertNoFileDownloaded();
        Livewire::actingAs($user)->test(ExitTable::class)->set('selectedExitRowIds', [$id])
            ->call('openExportModal')->assertForbidden();
    }

    public function test_invalid_sort_direction_is_normalized_before_querying_and_exporting(): void
    {
        $zeta = $this->createRow('Zeta');
        $alfa = $this->createRow('Alfa');
        $component = Livewire::withQueryParams(['sort' => 'customer', 'dir' => 'unexpected'])->test(ExitTable::class)
            ->assertSet('dir', 'asc')->set('selectedExitRowIds', [$zeta, $alfa])
            ->call('exportSelectedRows')->assertHasNoErrors()->assertFileDownloaded();
        $book = $this->readDownload($component);
        $this->assertSame('Alfa', $book->getActiveSheet()->getCell('A2')->getValue());
        $book->disconnectWorksheets();
        Livewire::test(ExitTable::class)->set('sort', 'customer')->set('dir', 'unexpected')->assertSet('dir', 'asc');
    }

    public function test_existing_signed_print_action_still_works(): void
    {
        $id = $this->createRow();
        $component = Livewire::test(ExitTable::class)->set('selectedExitRowIds', [$id])->call('printSelectedRows');
        $url = collect($component->effects['dispatches'])->firstWhere('name', 'open-print-window')['params']['url'];
        $this->get($url)->assertOk()->assertSee('Divano dimostrativo');
    }

    public function test_real_page_and_livewire_requests_support_selection_validation_and_download(): void
    {
        $id = $this->createRow();
        Route::middleware(['web', 'auth'])->get('/_test/warehouse-export', fn () => view('pages.warehouse.exit'));
        $html = $this->get('/_test/warehouse-export')->assertOk()->getContent();
        $document = new \DOMDocument();
        $oldErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($oldErrors);
        }
        $snapshot = null;
        foreach ($document->getElementsByTagName('div') as $element) {
            $candidate = $element->getAttribute('wire:snapshot');
            if ($candidate && data_get(json_decode($candidate, true), 'memo.name') === 'warehouse.exit-table') {
                $snapshot = $candidate;
                break;
            }
        }
        $this->assertNotNull($snapshot);
        $update = function (string $current, array $updates, ?string $method = null) {
            return $this->postJson('/livewire/update', ['components' => [[
                'snapshot' => $current,
                'updates' => (object) $updates,
                'calls' => $method ? [['path' => '', 'method' => $method, 'params' => []]] : [],
            ]]], ['X-Livewire' => 'true'])->assertOk()->json();
        };
        $selected = $update($snapshot, ['selectedExitRowIds.0' => (string) $id]);
        $opened = $update($selected['components'][0]['snapshot'], [], 'openExportModal');
        $invalid = $update($opened['components'][0]['snapshot'], ['selectedExportColumns' => []], 'exportSelectedRows');
        $this->assertStringContainsString('Seleziona almeno una colonna', $invalid['components'][0]['effects']['html']);
        $download = $update($invalid['components'][0]['snapshot'], [
            'selectedExportColumns.0' => 'customer',
            'selectedExportColumns.1' => 'color_note',
            'selectedExportColumns.2' => 'qty_in_phase',
        ], 'exportSelectedRows');
        $this->assertStringEndsWith('.xlsx', $download['components'][0]['effects']['download']['name']);
        $this->assertStringStartsWith('PK', base64_decode($download['components'][0]['effects']['download']['content']));

        /** Le fixture facoltative contengono esclusivamente dati dimostrativi del database in memoria. */
        if (getenv('WAREHOUSE_EXPORT_BROWSER_FIXTURES') === '1') {
            $directory = storage_path('framework/testing/warehouse-export-browser');
            if (!is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            file_put_contents($directory.'/page.html', $html);
            file_put_contents($directory.'/responses.json', json_encode(compact('selected', 'opened', 'invalid', 'download'), JSON_THROW_ON_ERROR));
        }
    }

    private function createRow(string $company = 'Cliente dimostrativo', string $number = '0012'): int
    {
        $customer = DB::table('customers')->insertGetId(['company' => $company]);
        $numberId = DB::table('order_numbers')->insertGetId(['number' => $number]);
        $product = DB::table('products')->insertGetId(['name' => 'Divano dimostrativo', 'sku' => 'TEST-01']);
        $order = DB::table('orders')->insertGetId([
            'customer_id' => $customer, 'order_number_id' => $numberId,
            'shipping_zone' => 'Zona di prova', 'reference' => 'Riferimento dimostrativo',
            'ordered_at' => '2026-11-06', 'delivery_date' => '2026-12-16',
        ]);
        $id = DB::table('order_items')->insertGetId(['order_id' => $order, 'product_id' => $product, 'quantity' => 3, 'unit_price' => 500.25]);
        $fabric = DB::table('fabrics')->insertGetId(['name' => 'Tessuto di prova', 'code' => 'TEST']);
        $color = DB::table('colors')->insertGetId(['name' => 'Blu', 'code' => '8']);
        DB::table('order_product_variables')->insert(['order_item_id' => $id, 'fabric_id' => $fabric, 'color_id' => $color, 'color_notes' => 'Nota dimostrativa']);
        return $id;
    }

    private function moveToShipping(int $id, float $quantity): void
    {
        DB::table('order_item_phase_events')->insert([
            'order_item_id' => $id, 'from_phase' => 0, 'to_phase' => 3,
            'quantity' => $quantity, 'changed_by' => $this->operator->id,
        ]);
    }

    private function readDownload(Testable $component): Spreadsheet
    {
        $component->assertFileDownloaded(contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'warehouse-export-test-');
        try {
            file_put_contents($path, base64_decode($component->effects['download']['content'], true));
            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }
}
