<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class AdminAssignableRolesSeeder extends Seeder
{
    /**
     * Esegue il seeder.
     */
    public function run(): void
    {
        // 1) Recupero il ruolo Admin
        $admin = Role::where('name', 'Admin')->first();

        if (! $admin) {
            $this->command->warn('⚠ Nessun ruolo Admin trovato, seeder saltato.');
            return;
        }

        // 2) Recupero TUTTI i ruoli (compreso Admin)
        $allRoles = Role::pluck('id')->toArray();

        // 3) Associo tutti i ruoli come assegnabili
        $admin->assignableRoles()->sync($allRoles);

        $this->command->info('✅ Ruolo Admin può assegnare tutti i ruoli (incluso Admin).');
    }
}
