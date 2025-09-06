<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot tra prodotti e componenti (distinta base), con quantità.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_components', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 8, 3)->comment('Numero di componenti per prodotto');
            $table->primary(['product_id','component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_components');
    }
};