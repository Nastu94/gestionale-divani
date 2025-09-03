<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Whitelist tessuti ammessi per ciascun prodotto + eventuale override di maggiorazione.
     */
    public function up(): void
    {
        Schema::create('product_fabrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fabric_id')->constrained('fabrics')->restrictOnDelete();
            $table->enum('surcharge_type', ['fixed','percent'])->nullable(); // Se null → usa default tessuto
            $table->decimal('surcharge_value', 10, 2)->nullable();           // Se null → usa default tessuto
            $table->boolean('is_default')->default(false);                   // Default pre-selezionato in UI
            $table->timestamps();

            $table->unique(['product_id','fabric_id']);                      // Un tessuto per prodotto una sola volta
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_fabrics');
    }
};
