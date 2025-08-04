<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('order_item_phase_events', function (Blueprint $table) {
            $table->enum('rollback_mode', ['reuse', 'scrap'])
                  ->nullable()
                  ->after('is_rollback')
                  ->comment('Solo per eventi di rollback: se reuse i pezzi sono riutilizzabili, se scrap andranno riordinati');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('order_item_phase_events', fn (Blueprint $t) => $t->dropColumn('rollback_mode'));
    }
};