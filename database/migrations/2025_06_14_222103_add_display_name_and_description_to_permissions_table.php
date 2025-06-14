<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge colonne per:
 *  - display_name: nome leggibile nel front-end
 *  - description: breve descrizione del permesso
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Nome da mostrare al front-end quando assegni i permessi
            $table->string('display_name')
                  ->after('guard_name')
                  ->comment('Nome visualizzato nel front-end');

            // Descrizione breve del permesso
            $table->string('description')
                  ->nullable()
                  ->after('display_name')
                  ->comment('Breve descrizione del permesso');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description']);
        });
    }
};
