<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Introduce lo "slot variabile" nella distinta base del prodotto.
     * is_variable = true identifica la riga placeholder (es. FABRIC_MAIN).
     */
    public function up(): void
    {
        Schema::table('product_components', function (Blueprint $table) {
            $table->boolean('is_variable')->default(false)->after('quantity'); // Placeholder?
            $table->string('variable_slot', 50)->nullable()->after('is_variable'); // Nome slot (es. FABRIC_MAIN)
            // NB: la quantità resta quella già presente in 'quantity' (es. metri per pezzo)
            $table->index(['is_variable','variable_slot']);
        });
    }

    public function down(): void
    {
        Schema::table('product_components', function (Blueprint $table) {
            $table->dropIndex(['is_variable','variable_slot']);
            $table->dropColumn(['is_variable','variable_slot']);
        });
    }
};
