<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Righe DDT: snapshot minimo di cosa stai spedendo.
 *
 * Salviamo quantity “spedita nel DDT” (tipicamente qty_in_phase in Spedizione).
 * Prezzo/unità lo fotografiamo per coerenza ristampa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ddt_rows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ddt_id')
                ->constrained('ddts')
                ->cascadeOnDelete()
                ->comment('DDT padre');

            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete()
                ->comment('Riga ordine collegata');

            $table->decimal('quantity', 10, 2)
                ->comment('Quantità inserita nel DDT');

            $table->decimal('unit_price', 12, 2)
                ->default(0)
                ->comment('Prezzo unitario fotografato (ivato, se così lavori)');

            $table->unsignedSmallInteger('vat',)
                ->default(22)
                ->comment('Aliquota IVA (es. 22)');

            $table->timestamps();

            /* Evita duplicati della stessa riga ordine nello stesso DDT */
            $table->unique(['ddt_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ddt_rows');
    }
};
