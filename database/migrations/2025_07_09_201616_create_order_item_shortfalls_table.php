<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantità mancanti su una riga d’ordine fornitore.
 *  - una riga per componente con qty > 0
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_shortfalls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_item_id')          // riga originale
                  ->constrained('order_items')
                  ->cascadeOnDelete();

            $table->decimal('quantity', 12, 3);         // mancante

            $table->string('note')->nullable();         // opzionale

            $table->timestamps();

            $table->unique('order_item_id');            // 1-a-1 con la riga
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_shortfalls');
    }
};
