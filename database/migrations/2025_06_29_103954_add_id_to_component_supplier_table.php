<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge la colonna id alla tabella pivot.
     */
    public function up(): void
    {
        Schema::table('component_supplier', function (Blueprint $table) {
            $table->dropForeign(['component_id']);
            $table->dropForeign(['supplier_id']);

            $table->dropPrimary(['component_id', 'supplier_id']);

            $table->bigIncrements('id')->first();

            $table->foreign('component_id')
                  ->references('id')
                  ->on('components')
                  ->cascadeOnDelete();

            $table->foreign('supplier_id')
                  ->references('id')
                  ->on('suppliers')
                  ->cascadeOnDelete();

            $table->unique(['component_id', 'supplier_id'], 'cmp_sup_unique');
        });
    }

    /**
     * Rollback: rimuove la colonna id.
     */
    public function down(): void
    {
        Schema::table('component_supplier', function (Blueprint $table) {
            $table->dropUnique('cmp_sup_unique');
            $table->dropForeign(['component_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('id');
            $table->primary(['component_id', 'supplier_id']);
            $table->foreign('component_id')
                  ->references('id')->on('components')->cascadeOnDelete();
            $table->foreign('supplier_id')
                  ->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }
};
