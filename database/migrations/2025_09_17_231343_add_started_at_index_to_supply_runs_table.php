<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge un indice su started_at (e id) per velocizzare la retention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_runs', function (Blueprint $table) {
            // Indice combinato utile per l'ordinamento di prune
            $table->index(['started_at', 'id'], 'supply_runs_started_at_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('supply_runs', function (Blueprint $table) {
            $table->dropIndex('supply_runs_started_at_id_idx');
        });
    }
};
