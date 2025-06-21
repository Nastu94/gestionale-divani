<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_delegations', function (Blueprint $table) {
            $table->foreignId('role_id')        // es. Supervisor
                ->constrained('roles')->cascadeOnDelete();
            $table->foreignId('delegable_id')   // es. Impiegato
                ->constrained('roles')->cascadeOnDelete();
            $table->primary(['role_id','delegable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_delegations');
    }
};
