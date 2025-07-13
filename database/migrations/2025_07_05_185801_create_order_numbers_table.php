<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_numbers', function (Blueprint $t) {
            $t->id();                                // usato come FK nella tabella orders
            $t->unsignedBigInteger('number');        // progressivo vero
            $t->string('order_type', 20);            // 'supplier' | 'customer'
            $t->timestamps();

            // nessun ‘unique’ sul singolo campo: la combinazione è unica
            $t->unique(['number', 'order_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_numbers');
    }
};
