<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Aggiunge il campo 'code' alla tabella 'warehouses'.
 *
 * - Ogni magazzino avrà un codice univoco in formato “MG-XXXX”.
 * - La colonna viene posizionata subito dopo 'name' grazie al metodo ->after().
 * - Nel down() la colonna viene rimossa per mantenerne la reversibilità.
 */
return new class extends Migration
{
    /**
     * Esegue la migrazione.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('code', 20)          // lunghezza più che sufficiente (es. MG-STOCK)
                  ->unique()                    // deve essere univoco
                  ->after('name')               // posizione subito dopo 'name'
                  ->comment('Codice univoco magazzino (es. MG-STOCK, MG-IMP)');            
            $table->softDeletes();
        });

        // Aggiunta di un record predefinito per il magazzino principale
        DB::table('warehouses')->insert([
            'name' => 'Magazzino Stoccaggio',
            'code' => 'MG-STOCK',
            'type' => 'stock',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],[
            'name'       => 'Magazzino Resi',
            'code'       => 'MG-RETURN',
            'type'       => 'return',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Annulla la migrazione.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
