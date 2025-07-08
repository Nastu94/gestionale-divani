<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_level_lots', function (Blueprint $table) {
            $table->foreignId('lot_number_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('lot_numbers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_level_lots', function (Blueprint $table) {
            $table->dropConstrainedColumns(['lot_number_id']);
        });
    }
};
