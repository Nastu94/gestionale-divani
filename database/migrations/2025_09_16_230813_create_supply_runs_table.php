<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    /**
    * Crea la tabella di telemetria dei run settimanali.
    * Ogni riga rappresenta un'esecuzione (tipicamente il lunedì).
    */
    public function up(): void
    {
        Schema::create('supply_runs', function (Blueprint $table) {
            $table->id();

            // Finestra trattata (oggi → oggi + window_days)
            $table->date('window_start');
            $table->date('window_end');

            // Etichetta settimanale ISO (es. 2025-W38) per la reportistica
            $table->string('week_label', 16)->index();

            // Timing esecuzione
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Metriche principali
            $table->unsignedInteger('orders_scanned')->default(0);
            $table->unsignedInteger('orders_skipped_fully_covered')->default(0);
            $table->unsignedInteger('orders_touched')->default(0);

            // Riserve da stock
            $table->unsignedInteger('stock_reservation_lines')->default(0);
            $table->decimal('stock_reserved_qty', 14, 3)->default(0);

            // Riserve da PO esistenti
            $table->unsignedInteger('po_reservation_lines')->default(0);
            $table->decimal('po_reserved_qty', 14, 3)->default(0);

            // Shortfall residuo & PO creati
            $table->unsignedInteger('components_in_shortfall')->default(0);
            $table->decimal('shortfall_total_qty', 14, 3)->default(0);
            $table->unsignedInteger('purchase_orders_created')->default(0);

            // Riferimenti e dettagli flessibili
            $table->json('created_po_ids')->nullable();
            $table->json('notes')->nullable();

            // Esito & errori
            $table->enum('result', ['ok', 'partial', 'error'])->default('ok')->index();
            $table->json('error_context')->nullable();

            // Metadati tecnici
            $table->uuid('trace_id')->nullable()->index();
            $table->json('meta')->nullable();

            $table->timestamps(); // created_at / updated_at
        });
    }

    /**
    * Elimina la tabella di telemetria.
    */
    public function down(): void
    {
        Schema::dropIfExists('supply_runs');
    }
};