<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indirizzi multipli per cliente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['billing','shipping','other'])->comment('Tipo indirizzo');
            $table->string('address')->comment('Via e numero civico');
            $table->string('city')->comment('Città');
            $table->string('postal_code')->comment('CAP');
            $table->string('country')->default('Italia')->comment('Nazione');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};