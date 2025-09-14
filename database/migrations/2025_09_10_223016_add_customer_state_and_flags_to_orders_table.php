<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrazione: aggiunge colonne per la gestione stato cliente e flag anonimo su orders.
 *
 * Campi aggiunti:
 * - hash_flag (bool): rappresenta l'“ordine nero” in forma anonima; in UI mostrerai un badge "#".
 * - note (text, nullable): note interne, visibili nella sidebar "Visualizza".
 * - status (bool): stato lato cliente (0=non confermato, 1=confermato).
 * - reason (text, nullable): motivazione del rifiuto (viene azzerata quando l'ordine viene poi confermato).
 * - confirmed_at (datetime, nullable): timestamp della conferma cliente (serve per la regola dei 30 giorni).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Flag anonimo per "ordine nero": default false (0).
            $table->boolean('hash_flag')
                ->default(false)
                ->after('total'); // TODO: sposta vicino al punto che preferisci

            // Note interne, opzionali.
            $table->text('note')
                ->nullable()
                ->after('hash_flag');

            // Stato lato cliente: 0 non confermato / 1 confermato.
            $table->boolean('status')
                ->default(false)
                ->after('note')
                ->index(); // utile per filtri elenco

            // Motivazione (solo quando rifiutato); si azzera alla successiva conferma.
            $table->text('reason')
                ->nullable()
                ->after('status');

            // Quando il cliente conferma (usata per calcolare delta < 30 giorni).
            $table->dateTime('confirmed_at')
                ->nullable()
                ->after('reason')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['hash_flag', 'note', 'stato', 'reason', 'confirmed_at']);
        });
    }
};
