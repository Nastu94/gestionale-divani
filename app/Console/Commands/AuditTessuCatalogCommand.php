<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TessuCatalogAuditService;
use Illuminate\Support\Facades\Log;

class AuditTessuCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tessu:catalog-audit 
                            {--fabric= : Comma-separated fabric IDs to filter}
                            {--color= : Comma-separated color IDs to filter}
                            {--product= : Comma-separated product IDs to check availability}
                            {--format=table : Output format: table, json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Esegue un audit read-only del catalogo TESSU per verificare mancanze e incoerenze.';

    /**
     * Execute the console command.
     */
    public function handle(TessuCatalogAuditService $auditService)
    {
        $this->info('Avvio audit catalogo TESSU (Modalità Read-Only)...');

        $fabricIds = $this->parseIds($this->option('fabric'));
        $colorIds = $this->parseIds($this->option('color'));
        $productIds = $this->parseIds($this->option('product'));
        $format = $this->option('format');

        $results = $auditService->audit($fabricIds, $colorIds, $productIds);

        if ($format === 'json') {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
            return 0;
        }

        // Print Global Catalog
        $this->info("\n=== Catalogo Globale ===");
        $globalHeaders = ['Fabric ID', 'Fabric Name', 'Color ID', 'Color Name', 'Component ID', 'Status'];
        $globalRows = $results['global_catalog']->map(function ($item) {
            return [
                $item['fabric_id'],
                $item['fabric_name'],
                $item['color_id'],
                $item['color_name'],
                $item['component_id'] ?? 'N/A',
                $this->formatStatus($item['status']),
            ];
        })->toArray();
        $this->table($globalHeaders, $globalRows);

        // Print Product Availability if requested
        if ($productIds) {
            $this->info("\n=== Disponibilità Prodotto ===");
            $prodHeaders = ['Product ID', 'Product Name', 'Fabric ID', 'Color ID', 'Component ID', 'Status'];
            $prodRows = $results['product_availability']->map(function ($item) {
                return [
                    $item['product_id'],
                    $item['product_name'],
                    $item['fabric_id'],
                    $item['color_id'],
                    $item['component_id'] ?? 'N/A',
                    $this->formatStatus($item['status']),
                ];
            })->toArray();
            $this->table($prodHeaders, $prodRows);
        }

        // Print Stats
        $this->info("\n=== Statistiche ===");
        $this->table(['Metric', 'Value'], collect($results['stats'])->map(fn($v, $k) => [$k, $v])->toArray());

        return 0;
    }

    private function parseIds(?string $option): ?array
    {
        if (!$option) return null;
        return array_map('intval', array_filter(explode(',', $option)));
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'valid' => '<fg=green>Valid</>',
            'missing_component' => '<fg=red>Missing Component</>',
            'inactive_component' => '<fg=yellow>Inactive Component</>',
            'archived_component' => '<fg=magenta>Archived Component</>',
            'archived_fabric' => '<fg=magenta>Archived Fabric</>',
            'archived_color' => '<fg=magenta>Archived Color</>',
            'inactive_fabric' => '<fg=yellow>Inactive Fabric</>',
            'inactive_color' => '<fg=yellow>Inactive Color</>',
            'wrong_category_or_invalid' => '<fg=red>Wrong Category/Invalid</>',
            default => $status,
        };
    }
}
