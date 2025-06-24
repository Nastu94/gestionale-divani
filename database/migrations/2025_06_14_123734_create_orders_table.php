<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordini di acquisto/produzione.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('cause', ['purchase','sale','return','scrap'])->comment('Causale ordine');
            $table->decimal('total', 14, 2)->default(0)->comment('Valore totale');
            $table->timestamp('ordered_at')->useCurrent()->comment('Data ordine');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};