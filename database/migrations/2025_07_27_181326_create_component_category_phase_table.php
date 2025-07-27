<?php
/**
 * Crea la tabella pivot «component_category_phase».
 *
 * • Collega una *categoria di componenti* alla *fase di produzione*
 *   in cui il materiale diventa indispensabile.
 * • Un’unica riga identifica l’uso di quella categoria in quella fase.
 *   Se domani servisse specificare più fasi, basterà inserire righe aggiuntive.
 *
 * Key & vincoli:
 *   - PK composta (category_id, phase)   → impedisce duplicati
 *   - FK su component_categories.id      → aggiorna/cancella a cascata
 *
 * NB: la colonna `phase` contiene il valore int dell’enum
 *     App\Enums\ProductionPhase (0-6).
 *
 * @author Gestionale Divani
 * @license Proprietary
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_category_phase', function (Blueprint $table) {
            // FK → component_categories.id
            $table->unsignedBigInteger('category_id');

            // fase di produzione (0-6)  – tinyint sufficiente
            $table->unsignedTinyInteger('phase');

            // PK composta per garantire unicità
            $table->primary(['category_id', 'phase']);

            // foreign key + cascade (se elimino la categoria, spariscono i link)
            $table->foreign('category_id')
                  ->references('id')
                  ->on('component_categories')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // timestamps (opzionali; utili per audit)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_category_phase');
    }
};
