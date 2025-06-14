<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prodotti finiti (modelli di divano).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->comment('Codice prodotto');
            $table->string('name')->comment('Nome prodotto');
            $table->text('description')->nullable()->comment('Descrizione dettagliata');
            $table->decimal('price', 12, 2)->comment('Prezzo unitario');
            $table->boolean('is_active')->default(true)->comment('Disponibilità');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};