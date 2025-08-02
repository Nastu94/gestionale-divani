<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('stock_level_lots', function (Blueprint $table) {
            $table->decimal('received_quantity', 15, 3)
                  ->after('quantity')
                  ->default(0);
        });

        // popola con i valori correnti (una tantum)
        DB::table('stock_level_lots')->update([
            'received_quantity' => DB::raw('quantity'),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('stock_level_lots', function (Blueprint $table) {
            $table->dropColumn('received_quantity');
        });
    }
};