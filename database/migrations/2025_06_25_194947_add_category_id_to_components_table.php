<?php
/**
 * Aggiunge la FK 'category_id' alla tabella 'components'.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->foreignId('category_id')
                  ->after('id')               // subito dopo la PK
                  ->constrained('component_categories')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();       // impedisce la cancellazione se usata
        });
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
