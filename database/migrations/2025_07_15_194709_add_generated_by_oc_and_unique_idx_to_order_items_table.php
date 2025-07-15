<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * • aggiunge generated_by_order_customer_id a order_items
 * • impone indice unico (order_id, component_id) per evitare
 *   righe duplicate nei PO generati automaticamente
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('generated_by_order_customer_id')
                  ->nullable()
                  ->after('order_id')
                  ->constrained('orders')
                  ->nullOnDelete();

            $table->unique(
                ['order_id', 'component_id'],
                'oi_order_component_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique('oi_order_component_unique');
            $table->dropForeign(['generated_by_order_customer_id']);
            $table->dropColumn('generated_by_order_customer_id');
        });
    }
};
