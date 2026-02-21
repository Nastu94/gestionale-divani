<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge "color_notes" alle variabili di riga (order_product_variables).
 *
 * Serve per gestire note interne su multi-colore / dettagli colore
 * mantenendo comunque un solo color_id per la logica di costing/slot.
 */
return new class extends Migration
{
    /**
     * Applica la migrazione.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('order_product_variables', function (Blueprint $table) {
            // Campo libero (potenzialmente più lungo di 255), nullable per retro-compatibilità.
            // Lo mettiamo vicino a fabric_id / color_id perché è concettualmente associato a quei dati.
            $table->text('color_notes')
                ->nullable()
                ->after('color_id');
        });
    }

    /**
     * Annulla la migrazione.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('order_product_variables', function (Blueprint $table) {
            // Rimuove la colonna aggiunta in up().
            $table->dropColumn('color_notes');
        });
    }
};