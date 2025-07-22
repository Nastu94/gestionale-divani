<?php

use App\Enums\ProductionPhase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colonna denormalizzata per popolare rapidamente le KPI sull’intestazione ordini.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->tinyInteger('min_phase')
                  ->default(ProductionPhase::INSERTED->value)
                  ->comment('Fase minima tra tutte le righe')
                  ->after('bill_number');

            $table->index('min_phase', 'idx_orders_min_phase');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_min_phase');
            $table->dropColumn('min_phase');
        });
    }
};
