<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge il campo "reference" alla tabella orders.
 *
 * Questo campo servirà come riferimento testuale da mostrare
 * nelle tabelle e nei documenti stampati.
 */
return new class extends Migration
{
    /**
     * Esegue le modifiche allo schema.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            /**
             * Campo riferimento opzionale.
             *
             * - nullable(): non tutti gli ordini potrebbero averlo
             * - after(): lo posizioniamo dopo bill_number per tenerlo
             *   vicino agli altri dati descrittivi/documentali
             */
            $table->string('reference')->nullable()->after('bill_number');
        });
    }

    /**
     * Annulla le modifiche allo schema.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            /**
             * Rimuove il campo reference in caso di rollback.
             */
            $table->dropColumn('reference');
        });
    }
};