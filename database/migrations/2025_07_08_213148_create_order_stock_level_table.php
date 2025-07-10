<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collega un ordine d’acquisto a una o più registrazioni di magazzino.
 *
 *  - order_id       ↔ orders.id
 *  - stock_level_id ↔ stock_levels.id
 *  - timestamps     per capire quando è stato creato il legame
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_stock_level', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('stock_level_lot_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->unique(['order_id', 'stock_level_lot_id']);  // evita doppi legami
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_stock_level');
    }
};
