<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Esempio con ENUM MariaDB / MySQL
            DB::statement("ALTER TABLE stock_movements 
                MODIFY COLUMN type ENUM('in','out','reserve','unreserve') NOT NULL");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Esempio con ENUM MariaDB / MySQL
            DB::statement("ALTER TABLE stock_movements 
                MODIFY COLUMN type ENUM('in','out','reserve') NOT NULL");
        });
    }
};
