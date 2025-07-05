<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            // 1. rimuovi i vecchi campi
            $t->dropColumn(['cause']);

            // 2. nuova FK al registro numeri
            $t->foreignId('order_number_id')
            ->after('customer_id')
            ->constrained('order_numbers')
            ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 1. rimuovi la FK
            $table->dropForeign(['order_number_id']);

            // 2. rimuovi il campo
            $table->dropColumn('order_number_id');

            // 3. ripristina i vecchi campi
            $table->string('cause')->nullable()->after('customer_id');
            $table->string('order_number')->nullable()->after('cause');
        });
    }
};
