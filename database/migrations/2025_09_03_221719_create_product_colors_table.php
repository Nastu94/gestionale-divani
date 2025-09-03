<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Whitelist colori ammessi per ciascun prodotto + eventuale override di maggiorazione.
     */
    public function up(): void
    {
        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('color_id')->constrained('colors')->restrictOnDelete();
            $table->enum('surcharge_type', ['fixed','percent'])->nullable(); // Se null → usa default colore
            $table->decimal('surcharge_value', 10, 2)->nullable();           // Se null → usa default colore
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['product_id','color_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_colors');
    }
};
