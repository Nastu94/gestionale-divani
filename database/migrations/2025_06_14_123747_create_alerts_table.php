<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifiche e avvisi generici.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type')->comment('Tipo avviso');
            $table->text('message')->comment('Testo avviso');
            $table->json('payload')->nullable()->comment('Dati aggiuntivi');
            $table->boolean('is_read')->default(false)->comment('Stato lettura');
            $table->timestamp('triggered_at')->useCurrent()->comment('Data creazione');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};