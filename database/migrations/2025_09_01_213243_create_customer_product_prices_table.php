<?php
// database/migrations/2025_09_01_000000_create_customer_product_prices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Crea la tabella 'customer_product_prices' per gestire i prezzi per cliente e prodotto
     * con validità nel tempo (versioning per intervalli disgiunti).
     */
    public function up(): void
    {
        Schema::create('customer_product_prices', function (Blueprint $table) {
            $table->id();

            // Riferimenti al prodotto e al cliente
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete(); // se elimino il prodotto, elimino le versioni

            $table->foreignId('customer_id')
                ->constrained()
                ->cascadeOnDelete(); // vedi nota: se preferisci RESTRICT, cambia qui

            // Prezzo NETTO (IVA esclusa), valuta di default EUR
            $table->decimal('price', 12, 2);
            $table->char('currency', 3)->default('EUR');

            // Range di validità: null = infinito (-∞/+∞). Intervalli disgiunti per coppia (product, customer)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // Annotazioni libere (facoltative)
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indici utili a risoluzione e liste
            $table->index(['product_id', 'customer_id'], 'idx_cpp_prod_cust');
            $table->index(['product_id', 'customer_id', 'valid_from', 'valid_to'], 'idx_cpp_validity');
        });
    }

    /**
     * Rollback tabella.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_product_prices');
    }
};
