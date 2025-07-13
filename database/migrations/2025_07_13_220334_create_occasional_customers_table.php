<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabella "occasional_customers" per gestire
 * clienti non censiti (guest) creati al volo dall’utente
 * con permesso orders.customer.create.
 *
 * Colonne essenziali all’emissione di un ordine:
 *  • dati fiscali (company, vat_number / tax_code)
 *  • indirizzo di consegna semplificato
 *  • contatti base (email, phone)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occasional_customers', function (Blueprint $table) {
            $table->id();

            // Ragione sociale / nominativo
            $table->string('company', 191);

            // Dati fiscali (opzionali)
            $table->string('vat_number', 20)->nullable();
            $table->string('tax_code', 20)->nullable();

            // Indirizzo di spedizione (campo unico semplificato)
            $table->string('address', 191)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('province', 10)->nullable();
            $table->string('country', 2)->default('IT');

            // Contatti
            $table->string('email', 191)->nullable();
            $table->string('phone', 30)->nullable();

            // Note interne
            $table->text('note')->nullable();

            $table->timestamps();

            // Indice di ricerca veloce (ragione sociale)
            $table->index('company');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occasional_customers');
    }
};
