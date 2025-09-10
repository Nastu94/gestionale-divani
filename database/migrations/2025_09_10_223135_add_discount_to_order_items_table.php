<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrazione: aggiunge 'discount' a order_items.
 *
 * - discount (text, nullable): JSON serializzato con tutti gli sconti della riga.
 *   Esempio valore: 
 *   [
 *     {"type":"percent","value":10,"label":"Promo X"},
 *     {"type":"fixed","value":25,"label":"Voucher Y"}
 *   ]
 *
 * Nota: useremo il cast Eloquent 'array' sul Model per leggere/scrivere automaticamente JSON
 * anche se la colonna è di tipo TEXT (non è obbligatorio il tipo JSON).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->text('discount')
                ->nullable()
                ->after('unit_price'); // TODO: posiziona dove preferisci
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('discount');
        });
    }
};
