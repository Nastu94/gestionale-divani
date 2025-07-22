<?php

use App\Enums\ProductionPhase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log atomico di ogni spostamento di quantità fra fasi.
     */
    public function up(): void
    {
        Schema::create('order_item_phase_events', function (Blueprint $table) {
            $table->id();

            // FK sulla riga d’ordine
            $table->foreignId('order_item_id')
                  ->constrained()
                  ->onDelete('cascade');

            // fase di partenza / arrivo (con vincolo applicativo: +1)
            $table->tinyInteger('from_phase')
                  ->comment('Fase di origine');
            $table->tinyInteger('to_phase')
                  ->comment('Fase di destinazione');

            // quantità spostata
            $table->decimal('quantity', 12, 2);

            // utente che ha effettuato l’operazione
            $table->foreignId('changed_by')
                  ->constrained('users')
                  ->onDelete('restrict');

            // rollback flag & motivazione eventuale
            $table->boolean('is_rollback')
                  ->default(false);
            $table->text('reason')
                  ->nullable();

            $table->timestamps();

            // indice composito per query lead-time
            $table->index(['order_item_id', 'to_phase'], 'idx_item_phase_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_phase_events');
    }
};
