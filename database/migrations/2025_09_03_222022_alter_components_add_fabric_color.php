<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Aggiunge i riferimenti a tessuto/colore sui Componenti.
     * Nota: la UNIQUE(fabric_id, color_id) è compatibile con MySQL: più NULL sono ammessi (altri componenti).
     */
    public function up(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->foreignId('fabric_id')->nullable()->after('category_id')->constrained('fabrics')->nullOnDelete();
            $table->foreignId('color_id')->nullable()->after('fabric_id')->constrained('colors')->nullOnDelete();

            // Unicità per combinazione tessuto×colore (vale per i componenti tessuto; altri avranno NULL e non collidono)
            $table->unique(['fabric_id','color_id'], 'components_fabric_color_unique');

            $table->index(['fabric_id']);
            $table->index(['color_id']);
        });
    }

    public function down(): void
    {
        Schema::table('components', function (Blueprint $table) {
            $table->dropUnique('components_fabric_color_unique');
            $table->dropConstrainedForeignId('color_id');
            $table->dropConstrainedForeignId('fabric_id');
        });
    }
};
