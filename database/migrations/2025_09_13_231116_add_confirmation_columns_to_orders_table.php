<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrazione non distruttiva per aggiungere i campi necessari
 * al flusso di conferma ordine dei clienti standard.
 *
 * NOTE:
 * - NON modifichiamo né rimuoviamo campi esistenti (status, confirmed_at, reason).
 * - Aggiungiamo solo:
 *   - confirm_token (uuid)       → per link pubblico one-time
 *   - confirmation_requested_at  → quando abbiamo inviato la richiesta
 *   - confirm_locale (string)    → lingua pagina conferma (es. 'it', 'en', 'it-IT')
 */
return new class extends Migration
{
    /**
     * Esegue la migrazione in avanti (aggiunta colonne).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Aggiungiamo le colonne solo se non già presenti,
            // così la migrazione resta idempotente anche in ambienti incoerenti.
            if (! Schema::hasColumn('orders', 'confirm_token')) {
                // UUID del token di conferma (univoco) per link pubblico.
                $table->uuid('confirm_token')->nullable()->after('reason');

                // Unique index sul token per garantire l'univocità a livello DB.
                $table->unique('confirm_token', 'orders_confirm_token_unique');
            }

            if (! Schema::hasColumn('orders', 'confirmation_requested_at')) {
                // Timestamp dell'invio della richiesta di conferma (per idempotenza / TTL).
                $table->dateTime('confirmation_requested_at')->nullable()->after('confirm_token')
                      ->index('orders_confirmation_requested_at_index');
            }

            if (! Schema::hasColumn('orders', 'confirm_locale')) {
                // Lingua preferita per la pagina pubblica di conferma (opzionale).
                // Uso lunghezza 10 per supportare formati tipo 'it-IT' o 'en-US'.
                $table->string('confirm_locale', 10)->nullable()->after('confirmation_requested_at')
                      ->index('orders_confirm_locale_index');
            }
        });
    }

    /**
     * Esegue il rollback (rimozione colonne).
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop indici prima delle colonne (se esistono), poi colonne.
            if (Schema::hasColumn('orders', 'confirm_token')) {
                // Rimuovo unique index se presente
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('orders');

                if (array_key_exists('orders_confirm_token_unique', $indexes)) {
                    $table->dropUnique('orders_confirm_token_unique');
                }

                $table->dropColumn('confirm_token');
            }

            if (Schema::hasColumn('orders', 'confirmation_requested_at')) {
                $table->dropIndex('orders_confirmation_requested_at_index');
                $table->dropColumn('confirmation_requested_at');
            }

            if (Schema::hasColumn('orders', 'confirm_locale')) {
                $table->dropIndex('orders_confirm_locale_index');
                $table->dropColumn('confirm_locale');
            }
        });
    }
};
