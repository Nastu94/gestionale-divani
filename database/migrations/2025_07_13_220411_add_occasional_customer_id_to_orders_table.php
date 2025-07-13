<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggiunge il riferimento opzionale "occasional_customer_id"
 * alla testata ordini.
 *
 * Logica:
 *  • Se il cliente è censito → order.customer_id non nullo
 *  • Se il cliente è saltuari​o → order.occasional_customer_id non nullo
 *  • I due campi restano mutuamente esclusivi a livello applicativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Colonna dopo customer_id per leggibilità
            $table->foreignId('occasional_customer_id')
                  ->nullable()
                  ->after('customer_id')
                  ->constrained('occasional_customers')
                  ->nullOnDelete();   // se il guest viene eliminato, la FK va a NULL
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['occasional_customer_id']);
            $table->dropColumn('occasional_customer_id');
        });
    }
};
