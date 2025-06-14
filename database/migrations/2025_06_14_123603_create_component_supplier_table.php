<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot many‑to‑many components ⇄ suppliers.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('component_supplier', function (Blueprint $table) {
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->integer('lead_time_days')->nullable();
            $table->decimal('last_cost', 10, 2)->nullable();
            $table->timestamps();

            $table->primary(['component_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_supplier');
    }
};