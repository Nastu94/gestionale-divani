<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // fase produzione (0..5). In spedizione usi DDT.
            $table->unsignedTinyInteger('phase');

            // numerazione progressiva per anno (tipo DDT)
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('number');

            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['year', 'number']);
            $table->index(['order_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
