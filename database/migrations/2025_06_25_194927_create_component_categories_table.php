<?php
/**
 * Crea la tabella 'component_categories'.
 *
 * Struttura:
 *  id            PK incrementale
 *  code          VARCHAR(3)  → prefisso alfa, unico
 *  name          VARCHAR(100)
 *  description   TEXT, facoltativa
 *  timestamps()  created_at / updated_at
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('component_categories', function (Blueprint $table) {
            $table->id();                               // PK
            $table->string('code', 5)->unique();        // Esempio: LEG, FAB, FDR
            $table->string('name', 100);                // Nome esteso: “Gambe”, “Tessuti”…
            $table->text('description')->nullable();    // Dettagli opzionali
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_categories');
    }
};
