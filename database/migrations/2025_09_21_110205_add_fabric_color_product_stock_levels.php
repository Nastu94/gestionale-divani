<?php
/**
 * Migration: Estende product_stock_levels per gestire variabili e prenotazioni OC.
 *  - Aggiunge fabric_id, color_id (FK nullable).
 *  - Converte reserved_for (VARCHAR) in BIGINT UNSIGNED NULL e crea FK su orders(id).
 *  - Aggiunge indici utili per le ricerche di allocazione.
 *
 * ATTENZIONE: l'ALTER di reserved_for presuppone che i valori esistenti (se presenti)
 *             siano numerici o NULL. Se in passato è stato usato testo libero,
 *             pulire o migrare i dati prima di eseguire questa migration.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock_levels', function (Blueprint $table) {
            // Colonne variabili (nullable): puntano alle tabelle master fabrics/colors.
            $table->foreignId('product_id')->after('warehouse_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('fabric_id')->nullable()->after('product_id')->constrained('fabrics')->nullOnDelete();
            $table->foreignId('color_id')->nullable()->after('fabric_id')->constrained('colors')->nullOnDelete();
        });

        // Cambiamo il tipo di reserved_for a BIGINT UNSIGNED NULL via SQL (no DBAL).
        // NB: usare il nome esatto della colonna esistente: "reserved_for".
        DB::statement('ALTER TABLE `product_stock_levels` MODIFY `reserved_for` BIGINT UNSIGNED NULL');

        // Aggiungiamo la FK su orders(id) + indice.
        Schema::table('product_stock_levels', function (Blueprint $table) {
            $table->foreign('reserved_for')
                  ->references('id')
                  ->on('orders')
                  ->nullOnDelete(); // se l'ordine viene cancellato, azzeriamo la prenotazione

            // Indici per match e lookup rapidi (magazzino resi + prodotto + variabili + stato prenotazione)
            $table->index(['warehouse_id', 'product_id', 'fabric_id', 'color_id', 'reserved_for'], 'psl_alloc_lookup_idx');
            $table->index('reserved_for', 'psl_reserved_for_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_stock_levels', function (Blueprint $table) {
            // Drop indici/FK in ordine inverso
            $table->dropIndex('psl_alloc_lookup_idx');
            $table->dropIndex('psl_reserved_for_idx');
            $table->dropForeign(['reserved_for']);

            // Rimuoviamo le FK variabili
            $table->dropConstrainedForeignId('fabric_id');
            $table->dropConstrainedForeignId('color_id');
        });

        // Torniamo a VARCHAR (se serve davvero il rollback).
        DB::statement('ALTER TABLE `product_stock_levels` MODIFY `reserved_for` VARCHAR(255) NULL');
    }
};
