<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella DDT (Documento di Trasporto).
 *
 * Salviamo numero progressivo annuale + dati base di trasporto.
 * Le righe prodotto stanno in ddt_rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ddts', function (Blueprint $table) {
            $table->id();

            /* FK ordine cliente */
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete()
                ->comment('Ordine cliente collegato al DDT');

            /* Numerazione progressiva annuale */
            $table->unsignedSmallInteger('year')
                ->comment('Anno della numerazione (es. 2026)');
            $table->unsignedBigInteger('number')
                ->comment('Numero progressivo DDT per anno');

            /* Data documento */
            $table->date('issued_at')
                ->comment('Data emissione DDT');

            /* Dati trasporto (footer proforma) */
            $table->string('carrier_name', 255)
                ->nullable()
                ->comment('Incaricato del trasporto');
            $table->string('transport_reason', 255)
                ->nullable()
                ->comment('Causale del trasporto');
            $table->unsignedInteger('packages')
                ->nullable()
                ->comment('Nr colli');
            $table->string('weight', 50)
                ->nullable()
                ->comment('Peso (testo libero, spesso arriva già formattato)');
            $table->string('goods_appearance', 255)
                ->nullable()
                ->comment('Aspetto esteriore dei beni');
            $table->string('port', 100)
                ->nullable()
                ->comment('Porto');
            $table->dateTime('transport_started_at')
                ->nullable()
                ->comment('Data e ora inizio trasporto');

            /* Audit */
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Utente che ha generato il DDT');

            $table->timestamps();

            /* Unicità numerazione: anno + numero */
            $table->unique(['year', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ddts');
    }
};
