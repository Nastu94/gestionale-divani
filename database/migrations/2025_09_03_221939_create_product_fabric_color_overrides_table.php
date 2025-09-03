<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Override eccezionali su coppia tessuto×colore PER PRODOTTO.
     * Si usa solo dove la somma (tessuto + colore) non basta.
     */
    public function up(): void
    {
        Schema::create('product_fabric_color_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fabric_id')->constrained('fabrics')->restrictOnDelete();
            $table->foreignId('color_id')->constrained('colors')->restrictOnDelete();
            $table->enum('surcharge_type', ['fixed','percent']);
            $table->decimal('surcharge_value', 10, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['product_id','fabric_id','color_id'], 'pfcov_prod_fab_col_uq');  // Un solo override per combinazione
            $table->index(['product_id'], 'pfcov_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_fabric_color_overrides');
    }
};
