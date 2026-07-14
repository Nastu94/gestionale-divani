<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $caseSql = "CASE
            WHEN %s = 0 THEN 0
            WHEN %s IN (1, 2) THEN 1
            WHEN %s IN (3, 4, 5) THEN 2
            WHEN %s = 6 THEN 3
            ELSE %s
        END";

        $buildUpdate = function (string $column) use ($caseSql) {
            return sprintf($caseSql, $column, $column, $column, $column, $column);
        };

        // order_items.current_phase
        DB::statement("UPDATE order_items SET current_phase = " . $buildUpdate('current_phase'));

        // orders.min_phase
        if (Schema::hasColumn('orders', 'min_phase')) {
            DB::statement("UPDATE orders SET min_phase = " . $buildUpdate('min_phase'));
        }

        // order_item_phase_events.from_phase and to_phase
        DB::statement("UPDATE order_item_phase_events SET from_phase = " . $buildUpdate('from_phase') . ", to_phase = " . $buildUpdate('to_phase'));

        // work_orders.phase
        if (Schema::hasTable('work_orders') && Schema::hasColumn('work_orders', 'phase')) {
            DB::statement("UPDATE work_orders SET phase = " . $buildUpdate('phase'));
        }

        // component_category_phase
        if (Schema::hasTable('component_category_phase')) {
            $newPhaseExpr = sprintf($caseSql, 'phase', 'phase', 'phase', 'phase', 'phase');
            
            DB::statement("
                CREATE TEMPORARY TABLE temp_ccp AS
                SELECT category_id,
                       $newPhaseExpr AS new_phase,
                       MIN(created_at) AS created_at,
                       MAX(updated_at) AS updated_at
                FROM component_category_phase
                GROUP BY category_id, new_phase
            ");

            DB::statement("DELETE FROM component_category_phase");

            DB::statement("
                INSERT INTO component_category_phase (category_id, phase, created_at, updated_at)
                SELECT category_id, new_phase, created_at, updated_at FROM temp_ccp
            ");

            DB::statement("DROP TEMPORARY TABLE temp_ccp");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new \Exception("Questa migrazione è irreversibile in modo deterministico. Utilizzare un backup del database per il ripristino.");
    }
};
