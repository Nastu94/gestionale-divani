<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_return_lines', function (Blueprint $table) {
            $table->id();

            // Appartenenza alla testata reso.
            $table->foreignId('product_return_id')->constrained('product_returns')->cascadeOnDelete();

            // Prodotto finito (divano).
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();

            // Variabili (nullable): match con PSL e con OC.
            $table->foreignId('fabric_id')->nullable()->constrained('fabrics')->nullOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();

            // Quantità rientrata (intera).
            $table->unsignedInteger('quantity');

            // Se TRUE, la riga viene messa a stock nel magazzino selezionato (di default: MG-RETURN).
            $table->boolean('restock')->default(false);

            // Magazzino di rientro (nullable per i resi solo amministrativi).
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

            // Condizione del reso (stringa snella; puoi trasformarla in enum DB se preferisci).
            $table->string('condition', 20)->default('A'); // es. A/B/C/REFURB/SCRAP

            // Motivo reso (breve).
            $table->string('reason', 50)->default('altro');

            // Note riga (opzionali).
            $table->text('notes')->nullable();

            // Collegamento alla riga PSL creata quando restock=true (utile per audit/allocazione).
            $table->foreignId('product_stock_level_id')->nullable()->constrained('product_stock_levels')->nullOnDelete();

            $table->timestamps();

            // Indici utili per filtri veloci lato UI
            $table->index(['product_id', 'fabric_id', 'color_id'], 'prl_product_variants_idx');
            $table->index(['restock', 'warehouse_id'], 'prl_restock_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_return_lines');
    }
};
