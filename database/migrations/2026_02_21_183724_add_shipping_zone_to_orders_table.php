<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge il campo "shipping_zone" (zona spedizione / nota interna)
 * alla tabella orders.
 *
 * - Non impatta la logica: è un semplice campo informativo da stampare nei documenti.
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
        Schema::table('orders', function (Blueprint $table) {
            // Campo testuale interno (nota), nullable per compatibilità con ordini già esistenti.
            // Lo posizioniamo dopo shipping_address per tenere l'header ordine "raggruppato".
            $table->string('shipping_zone', 255)
                ->nullable()
                ->after('shipping_address');
        });
    }

    /**
     * Annulla la migrazione.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Rimuove la colonna aggiunta in up().
            $table->dropColumn('shipping_zone');
        });
    }
};