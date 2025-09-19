<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
    * Aggiunge la colonna "operator" alla tabella degli eventi di fase.
    * - stringa corta (max 100) per nome/codice operatore di reparto
    * - nullable: opzionale lato UI
    */
    public function up(): void
    {
        Schema::table('order_item_phase_events', function (Blueprint $table) {
            $table->string('operator', 100)
            ->nullable()
            ->after('changed_by')
            ->comment("Nome o codice dell'operatore che ha eseguito fisicamente l'operazione");
        });
    }


    /**
    * Rollback della modifica.
    */
    public function down(): void
    {
        Schema::table('order_item_phase_events', function (Blueprint $table) {
            $table->dropColumn('operator');
        });
    }
};