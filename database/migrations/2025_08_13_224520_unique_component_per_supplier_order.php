<?php

/**
 * Impone che in una PO (order_type='supplier') non possano esistere
 * due righe con lo stesso component_id.
 *
 * NOTA: permette molteplici righe con component_id NULL (es. prodotti),
 * quindi non interferisce con altri tipi di righe.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unique(['order_id', 'component_id'], 'uk_order_items_order_component');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique('uk_order_items_order_component');
        });
    }
};
