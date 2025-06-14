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
            $table->string('internal_lot_code')->comment('Codice lotto interno');
            $table->string('supplier_lot_code')->nullable()->comment('Codice lotto fornitore');
            $table->decimal('quantity', 12, 2)->default(0)->comment('Quantità disponibile');
            $table->timestamps();
            $table->unique(['component_id', 'warehouse_id', 'internal_lot_code'], 'stock_unique_lot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};