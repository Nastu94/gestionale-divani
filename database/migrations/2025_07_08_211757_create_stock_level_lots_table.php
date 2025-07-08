<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M:N fra stock_levels e lotti.
 * Ogni entry = un lotto interno per una certa quantità.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_level_lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_level_id')
                    ->constrained()
                    ->cascadeOnDelete();

            $table->string('internal_lot_code', 50);
            $table->string('supplier_lot_code', 50)->nullable();
            $table->decimal('quantity', 12, 3)->default(0);

            $table->timestamps();

            $table->unique(['stock_level_id', 'internal_lot_code'],
                            'stock_level_internal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_level_lots');
    }
};
