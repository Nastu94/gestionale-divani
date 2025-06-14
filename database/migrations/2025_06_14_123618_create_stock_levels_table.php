<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giacenze correnti per componente e deposito.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0)->comment('Quantità in stock');
            $table->timestamps();
            $table->unique(['component_id','warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};