<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella che “prenota” la merce in arrivo su un PO per un
 * determinato Ordine Cliente, così da non contarla due volte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_item_id')      // riga PO
                  ->constrained('order_items')
                  ->cascadeOnDelete();

            $table->foreignId('order_customer_id')  // OC che prenota
                  ->constrained('orders')
                  ->cascadeOnDelete();

            $table->decimal('quantity', 12, 4);

            /* un OC può prenotare una sola volta la stessa riga PO */
            $table->unique(['order_item_id', 'order_customer_id']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_reservations');
    }
};
