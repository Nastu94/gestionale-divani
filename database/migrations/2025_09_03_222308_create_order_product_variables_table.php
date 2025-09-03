<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Traccia per ogni riga ordine (order_item) la scelta tessuto/colore,
     * il componente reale risolto e lo snapshot delle maggiorazioni applicate.
     */
    public function up(): void
    {
        Schema::create('order_product_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('slot', 50)->default('FABRIC_MAIN');     // Slot variabile a cui appartiene la scelta
            $table->foreignId('fabric_id')->nullable()->constrained('fabrics')->nullOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();
            $table->foreignId('resolved_component_id')->nullable()->constrained('components')->nullOnDelete();

            // Snapshot delle maggiorazioni applicate sulla riga ordine
            $table->decimal('surcharge_fixed_applied', 10, 2)->default(0);   // Somma € applicata
            $table->decimal('surcharge_percent_applied', 5, 2)->default(0);  // Somma % applicata
            $table->decimal('surcharge_total_applied', 10, 2)->default(0);   // Totale € aggiunti (dopo calcolo %)

            $table->timestamp('computed_at')->useCurrent();                  // Momento del calcolo (per storicità)
            $table->timestamps();

            $table->index(['order_item_id','slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_product_variables');
    }
};
