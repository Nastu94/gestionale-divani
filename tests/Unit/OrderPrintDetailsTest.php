<?php

namespace Tests\Unit;

use App\Enums\ProductionPhase;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Concerns\ManagesLoops;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class OrderPrintDetailsTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $container = new class(dirname(__DIR__, 2)) extends Container {
            public function __construct(private string $root) {}

            public function publicPath($path = ''): string
            {
                return $this->root.'/public'.($path !== '' ? '/'.$path : '');
            }
        };
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public static function productionPhases(): array
    {
        return [['Inserito', 0], ['Cucito', 1], ['Assemblaggio', 2]];
    }

    #[DataProvider('productionPhases')]
    public function test_work_order_repeats_original_order_details_without_replacing_issue_date(string $label, int $phase): void
    {
        $html = $this->renderWorkOrder($this->order(), $phase);
        $xpath = $this->xpath($html);
        $heading = $xpath->query('//table[@class="items"]/thead')->item(0)->textContent;

        $this->assertStringContainsString('03/10/2026', $heading);
        $this->assertStringContainsString('RIF-CLIENTE-A', $heading);
        $this->assertStringContainsString($label, $heading);
        $this->assertStringContainsString('19/11/2026', $html);
    }

    public function test_shipping_lines_keep_each_orders_date_and_reference_on_every_page(): void
    {
        $first = $this->order();
        $second = $this->order(900002, 'RIF-CLIENTE-B', '2026-10-07');
        $orders = array_merge(array_fill(0, 6, $first), [$second]);
        $html = $this->renderDdt($orders);
        $xpath = $this->xpath($html);
        $pages = $xpath->query('//div[@class="page"]');

        $this->assertCount(2, $pages);
        foreach ($pages as $pageIndex => $page) {
            $details = $xpath->query('.//div[@class="row-order-details"]', $page);
            $this->assertCount($pageIndex === 0 ? 6 : 1, $details);
            foreach ($details as $detail) {
                $this->assertStringContainsString($pageIndex === 0 ? '03/10/2026' : '07/10/2026', $detail->textContent);
                $this->assertStringContainsString($pageIndex === 0 ? 'RIF-CLIENTE-A' : 'RIF-CLIENTE-B', $detail->textContent);
                $this->assertStringNotContainsString($pageIndex === 0 ? 'RIF-CLIENTE-B' : 'RIF-CLIENTE-A', $detail->textContent);
                $this->assertStringNotContainsString('19/11/2026', $detail->textContent);
            }
            $this->assertStringContainsString('19/11/2026', $page->textContent);
        }
    }

    public function test_grouped_ddt_keeps_references_with_the_corresponding_articles(): void
    {
        $orders = [$this->order(), $this->order(900002, 'RIF-CLIENTE-B', '2026-10-07')];
        $xpath = $this->xpath($this->renderDdt($orders));
        $details = $xpath->query('//div[@class="row-order-details"]');

        $this->assertCount(2, $details);
        foreach ($orders as $index => $order) {
            $this->assertStringContainsString((string) $order->id, $details->item($index)->textContent);
            $this->assertStringContainsString($order->reference, $details->item($index)->textContent);
            $this->assertStringContainsString($order->ordered_at->format('d/m/Y'), $details->item($index)->textContent);
        }
    }

    public static function optionalReferences(): array
    {
        return [[null, 'Non indicato'], ['   ', 'Non indicato'], ['0', '0']];
    }

    #[DataProvider('optionalReferences')]
    public function test_missing_order_date_is_not_replaced_by_issue_date_and_zero_reference_is_kept(?string $reference, string $expected): void
    {
        $order = $this->order(900001, $reference, null);
        $workOrder = $this->xpath($this->renderWorkOrder($order, 0));
        $workDetails = $workOrder->query('//div[@class="order-details"]');
        $this->assertStringContainsString('Data ordine: Non indicata', $workDetails->item(0)->textContent);
        $this->assertStringContainsString('Riferimento cliente: '.$expected, $workDetails->item(1)->textContent);

        $ddt = $this->xpath($this->renderDdt([$order]));
        $details = $ddt->query('//div[@class="row-order-details"]')->item(0)->textContent;
        $this->assertStringContainsString('Data ordine: Non indicata', $details);
        $this->assertStringContainsString('Riferimento cliente: '.$expected, $details);
        $this->assertStringNotContainsString('19/11/2026', $details);
    }

    public function test_references_are_printed_as_text_without_interpreting_html(): void
    {
        $reference = '<script>alert("prova")</script> & riferimento';
        $order = $this->order(900001, $reference);
        foreach ([$this->renderWorkOrder($order, 1), $this->renderDdt([$order])] as $html) {
            $this->assertStringContainsString(e($reference), $html);
            $this->assertStringNotContainsString($reference, $html);
            $this->assertCount(0, $this->xpath($html)->query('//script'));
        }
    }

    public static function allPhases(): array
    {
        return array_map(fn (ProductionPhase $phase) => [$phase->value], ProductionPhase::cases());
    }

    #[DataProvider('allPhases')]
    public function test_selected_table_keeps_original_order_details_in_every_phase(int $phase): void
    {
        $row = (object) [
            'customer' => 'Cliente dimostrativo', 'shipping_zone' => 'Zona dimostrativa',
            'order_number' => '900001', 'reference' => 'RIF-CLIENTE-A',
            'product_name' => 'Divano dimostrativo', 'order_date' => '2026-10-03',
            'delivery_date' => '2026-12-15', 'value' => 100, 'qty_in_phase' => 1,
        ];
        $html = $this->renderBlade('pages/warehouse/exits-print-selected', [
            'rows' => collect([$row]), 'phase' => $phase,
            'phaseLabel' => ProductionPhase::from($phase)->label(),
            'printedAt' => Carbon::parse('2026-11-19 10:20:00'),
        ]);
        $body = $this->xpath($html)->query('//tbody')->item(0)->textContent;
        $this->assertStringContainsString('03/10/2026', $body);
        $this->assertStringContainsString('RIF-CLIENTE-A', $body);
    }

    private function order(int $id = 900001, ?string $reference = 'RIF-CLIENTE-A', ?string $date = '2026-10-03'): stdClass
    {
        return (object) [
            'id' => $id, 'orderNumber' => (object) ['number' => (string) $id],
            'ordered_at' => $date !== null ? Carbon::parse($date) : null,
            'reference' => $reference, 'shipping_zone' => 'Zona dimostrativa',
            'shipping_address' => 'Indirizzo dimostrativo', 'occasional_customer_id' => null,
            'customer' => (object) ['company' => 'Cliente dimostrativo', 'shippingAddress' => null],
            'occasionalCustomer' => null, 'note' => null,
        ];
    }

    private function item(stdClass $order): stdClass
    {
        return (object) [
            'id' => 800001, 'order_id' => $order->id, 'order' => $order, 'variable' => null,
            'product' => (object) ['sku' => 'TEST-001', 'name' => 'Divano dimostrativo', 'components' => collect()],
        ];
    }

    private function renderWorkOrder(stdClass $order, int $phase): string
    {
        $wo = (object) [
            'order' => $order, 'order_id' => $order->id, 'phase' => $phase,
            'number' => 700001, 'year' => 2026, 'issued_at' => Carbon::parse('2026-11-19 10:20:00'),
            'lines' => collect([(object) [
                'product_sku' => 'TEST-001', 'product_name' => 'Divano dimostrativo',
                'fabric' => 'Tessuto dimostrativo', 'color' => 'Colore dimostrativo',
                'qty' => 1, 'orderItem' => $this->item($order),
            ]]),
        ];

        return $this->renderBlade('pdf/work-order', ['wo' => $wo, 'resolvedMap' => collect(), 'logoDataUri' => null]);
    }

    private function renderDdt(array $orders): string
    {
        $rows = collect($orders)->map(fn (stdClass $order) => (object) [
            'orderItem' => $this->item($order), 'quantity' => 1, 'unit_price' => 100, 'vat' => 22,
        ]);
        $ddt = (object) [
            'order' => $orders[0], 'number' => 600001, 'issued_at' => Carbon::parse('2026-11-19'),
            'rows' => $rows, 'transport_started_at' => null,
        ];
        $party = ['company' => 'Cliente dimostrativo', 'address' => 'Indirizzo dimostrativo', 'tax_code' => '', 'vat_number' => ''];

        return $this->renderBlade('pdf/ddt', [
            'ddt' => $ddt, 'recipient' => $party, 'destination' => $party,
            'recipientCityLine' => 'Localita dimostrativa', 'destinationCityLine' => 'Localita dimostrativa',
            'pages' => $rows->values()->chunk(6),
        ]);
    }

    private function renderBlade(string $view, array $data): string
    {
        /** La compilazione resta in memoria: questi test non avviano il gestionale e non scrivono cache o dati. */
        $compiler = new BladeCompiler(new Filesystem(), 'unused-memory-only', '', false);
        $compiled = $compiler->compileString(file_get_contents(dirname(__DIR__, 2).'/resources/views/'.$view.'.blade.php'));
        $__env = new class { use ManagesLoops; };
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            eval('?>'.$compiled);
            return ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }
}
