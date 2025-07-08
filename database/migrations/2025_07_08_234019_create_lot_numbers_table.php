<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_numbers', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();           // AA000…
            $table->enum('status', ['reserved', 'confirmed'])
                  ->default('reserved');

            $table->foreignId('reserved_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('stock_level_lot_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_numbers');
    }
};
