<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella anagrafica fornitori.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vat_number')->nullable()->unique();
            $table->string('tax_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable(); // {street, city, zip, country}
            $table->string('website')->nullable();
            $table->text('payment_terms')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};