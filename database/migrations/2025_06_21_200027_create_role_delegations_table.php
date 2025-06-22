<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea pivot role_assignable_roles:
     * per ogni ruolo (parent) indica quali altri ruoli (child) può assegnare.
     */
    public function up(): void
    {
        Schema::create('role_assignable_roles', function (Blueprint $table) {
            $table->foreignId('role_id')
                  ->constrained('roles')
                  ->cascadeOnDelete()
                  ->comment('Ruolo che assegna');

            $table->foreignId('assignable_role_id')
                  ->constrained('roles')
                  ->cascadeOnDelete()
                  ->comment('Ruolo che può essere assegnato');

            $table->primary(['role_id', 'assignable_role_id'], 'role_assignable_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignable_roles');
    }
};
