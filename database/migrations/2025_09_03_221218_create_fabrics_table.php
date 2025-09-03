<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Crea la tabella dei tessuti (catalogo attributi globale).
     * Qui definiamo anche una maggiorazione di default (fissa o %) usata come fallback.
     */
    public function up(): void
    {
        Schema::create('fabrics', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                  // Nome del tessuto (es. Lino, Seta)
            $table->string('code')->nullable();                      // Codice interno opzionale
            $table->enum('surcharge_type', ['fixed','percent'])       // Tipo maggiorazione: 'fixed' (€/pz) o 'percent' (% su prezzo base)
                  ->default('fixed');
            $table->decimal('surcharge_value', 10, 2)->default(0);    // Valore maggiorazione: se percent → 0..100 con 2 decimali
            $table->boolean('active')->default(true);                 // Abilitazione in catalogo
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name']);                                // Evita duplicati logici
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabrics');
    }
};
