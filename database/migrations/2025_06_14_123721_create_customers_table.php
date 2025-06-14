<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anagrafica clienti.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->comment('Nome cliente');
            $table->string('last_name')->comment('Cognome cliente');
            $table->string('company')->nullable()->comment('Società');
            $table->string('email')->nullable()->comment('Email contatto');
            $table->string('phone')->nullable()->comment('Telefono');
            $table->boolean('is_active')->default(true)->comment('Cliente attivo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};