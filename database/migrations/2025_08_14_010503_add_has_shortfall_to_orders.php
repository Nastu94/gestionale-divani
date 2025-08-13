<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Flag denormalizzato: TRUE se per questo ordine è stato creato almeno uno shortfall
            $table->boolean('has_shortfall')->default(false)->after('parent_order_id');
            $table->index(['has_shortfall', 'order_number_id'], 'ix_orders_has_shortfall_number');
        });

        // Backfill: imposta a TRUE per tutti gli ordini che hanno almeno UNA riga con shortfall
        DB::statement("
            UPDATE orders o
            SET o.has_shortfall = 1
            WHERE EXISTS (
                SELECT 1
                FROM order_items oi
                JOIN order_item_shortfalls ois ON ois.order_item_id = oi.id
                WHERE oi.order_id = o.id
            )
        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('ix_orders_has_shortfall_number');
            $table->dropColumn('has_shortfall');
        });
    }
};
