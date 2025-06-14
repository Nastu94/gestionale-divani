<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storico di tutte le entrate/uscite di magazzino.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_level_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['in','out'])->comment('Tipo movimento');
            $table->decimal('quantity', 12, 2)->comment('Quantità movimentata');
            $table->text('note')->nullable()->comment('Note/motivazione');
            $table->timestamp('moved_at')->useCurrent()->comment('Data del movimento');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};