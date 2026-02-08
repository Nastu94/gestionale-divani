<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_order_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();

            // quantità stampata su questo buono (delta)
            $table->decimal('qty', 10, 2);

            // snapshot “leggibile” (così ristampa identica anche se cambi nomi)
            $table->string('product_name')->nullable();
            $table->string('product_sku')->nullable();
            $table->string('fabric')->nullable();
            $table->string('color')->nullable();

            $table->timestamps();

            $table->index(['order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_lines');
    }
};
