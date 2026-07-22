<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== QUERY 1 ===\n";
$q1 = DB::select("
SELECT
    c.id AS component_id,
    c.code,
    COUNT(sl.id) AS numero_stock_levels,
    GROUP_CONCAT(
        CONCAT(
            'ID=', sl.id,
            ', warehouse=', sl.warehouse_id,
            ', qty=', sl.quantity
        )
        ORDER BY sl.created_at, sl.id
        SEPARATOR ' | '
    ) AS livelli
FROM components c
LEFT JOIN stock_levels sl
    ON sl.component_id = c.id
WHERE c.id = 913
GROUP BY c.id, c.code
");
print_r($q1);

echo "\n=== QUERY 2 ===\n";
$q2 = DB::select("
SELECT
    id,
    order_item_id,
    from_phase,
    to_phase,
    quantity,
    is_rollback,
    rollback_mode,
    created_at
FROM order_item_phase_events
WHERE order_item_id = 2421
  AND rollback_mode = 'reuse'
ORDER BY id DESC
");
print_r($q2);
