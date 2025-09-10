<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crea tabella "product_stock_levels" minimale per prodotti finiti rivendibili (da reso).
 * FK NOMINATE ESPRESSAMENTE per evitare nomi anomali (es. "1") imposti da macro o estensioni.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_levels', function (Blueprint $table): void {
            // Forza InnoDB per sicurezza con FK (specie su MariaDB)
            $table->engine = 'InnoDB';

            $table->id();

            // FK esplicite: niente 'constrained()' per evitare nomi generati male
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('warehouse_id');

            $table->decimal('quantity', 10, 2)->default(0);
            $table->string('reserved_for')->nullable();

            $table->timestamps();

            /**
             * VINCOLI FK CON NOMI ESPLICITI E UNIVOCI
             * - Evitiamo qualsiasi nome numerico o generato da macro ('1', '2', …)
             * - Usiamo nomi stabili e descrittivi: 'psl_order_id_fk' e 'psl_warehouse_id_fk'
             */
            $table->foreign('order_id', 'psl_order_id_fk')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('cascade');

            $table->foreign('warehouse_id', 'psl_warehouse_id_fk')
                  ->references('id')
                  ->on('warehouses')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_levels');
    }
};
