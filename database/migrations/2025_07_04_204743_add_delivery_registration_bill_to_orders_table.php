<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggiunge le colonne alla tabella esistente.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Data prevista di consegna (obbligatoria per gli ordini fornitore)
            $table->date('delivery_date')
                  ->after('ordered_at')
                  ->comment('Data prevista di consegna');

            // Data di registrazione a magazzino, verrà popolata al carico merce
            $table->date('registration_date')
                  ->nullable()
                  ->after('delivery_date')
                  ->comment('Data registrazione magazzino (nullable)');

            // Numero bolla/DDT fornita dal fornitore
            $table->string('bill_number', 50)
                  ->nullable()
                  ->after('registration_date')
                  ->comment('Numero bolla di consegna (nullable)');
        });
    }

    /**
     * Roll-back: elimina le colonne se la migration viene revertita.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_date',
                'registration_date',
                'bill_number',
            ]);
        });
    }
};