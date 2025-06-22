<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge i campi vat_number e tax_code alla tabella customers.
 */
return new class extends Migration {
    /**
     * Esegue le modifiche allo schema: aggiunge le colonne.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Partita IVA del cliente (nullable)
            $table->string('vat_number')->nullable()->comment('Partita IVA')->after('company');
            // Codice fiscale del cliente (nullable)
            $table->string('tax_code')->nullable()->comment('Codice fiscale')->after('vat_number');
        });
    }

    /**
     * Ripristina lo schema allo stato precedente: rimuove le colonne aggiunte.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Rimuove entrambe le colonne in un unico comando
            $table->dropColumn(['vat_number', 'tax_code']);
        });
    }
};
