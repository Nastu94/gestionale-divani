<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Crea la tabella dei colori (catalogo attributi globale).
     * Anche qui c'è una maggiorazione di default usata come fallback.
     */
    public function up(): void
    {
        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                  // Nome colore (es. Rosso)
            $table->string('code')->nullable();                      // Codice interno opzionale (es. R-01)
            $table->char('hex', 7)->nullable();                      // Swatch esadecimale (#RRGGBB)
            $table->enum('surcharge_type', ['fixed','percent'])->default('fixed');
            $table->decimal('surcharge_value', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name']);                                // Evita duplicati logici
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colors');
    }
};
