<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giacenze correnti: quantità per componente,
 * deposito e lotto.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete()->comment('Componente associato');
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete()->comment('Deposito associato');
            $table->decimal('quantity', 12, 2)->default(0)->comment('Quantità totale disponibile');
            $table->timestamps();
            $table->unique(['component_id', 'warehouse_id'], 'stock_unique_lot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};