<?php

use App\Enums\ProductionPhase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge i campi necessari alla gestione della produzione
     * “a tranche” direttamente sulla riga d’ordine.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // fase più arretrata tra i pezzi ancora da produrre
            $table->tinyInteger('current_phase')
                  ->default(ProductionPhase::INSERTED->value)
                  ->comment('Fase minima fra i pezzi non ancora completati')
                  ->after('quantity');

            // quantità totale già avanzata oltre current_phase
            $table->decimal('qty_completed', 12, 2)
                  ->default(0)
                  ->after('current_phase');

            // timestamp ultimo cambio di fase per ordinamenti rapidi
            $table->timestamp('phase_updated_at')
                  ->nullable()
                  ->after('qty_completed');

            // indice usato dalle KPI card e dai filtri
            $table->index('current_phase', 'idx_order_items_phase');
        });
    }

    /**
     * Roll-back migration.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('idx_order_items_phase');
            $table->dropColumn(['current_phase', 'qty_completed', 'phase_updated_at']);
        });
    }
};
