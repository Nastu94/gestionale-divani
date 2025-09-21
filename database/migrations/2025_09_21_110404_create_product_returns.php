<?php
/**
 * Migration: Crea le tabelle per la gestione dei resi (testata + righe).
 *
 * - product_returns: testata del reso (numero, cliente, data, note, riferimenti).
 * - product_return_lines: righe del reso (prodotto, variabili, qty, restock/magazzino, condizione, motivo).
 *   Se restock=true, potremo collegare la riga alla PSL creata tramite product_stock_level_id.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();

            // Numero reso (es. RE-2025-000123) - unico per facile ricerca.
            $table->string('number')->unique();

            // Cliente che effettua il reso.
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnUpdate()->restrictOnDelete();

            // Riferimento eventuale ad un ordine cliente sorgente (opzionale).
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            // Data del reso (usiamo DATE per semplificare il filtro per giorno).
            $table->date('return_date');

            // Note testata (opzionali).
            $table->text('notes')->nullable();

            // Utente che ha registrato il reso (opzionale).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });


    }

    public function down(): void
    {
        Schema::dropIfExists('product_returns');
    }
};
