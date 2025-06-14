<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea l'utente amministratore e gli assegna il ruolo 'Admin'.
     */
    public function run()
    {
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name'     => env('ADMIN_NAME', 'Administrator'),
                'password' => bcrypt(env('ADMIN_PASSWORD', 'secret')),
            ]
        );

        // Assegna il ruolo Admin (tutti i permessi)
        $admin->assignRole('Admin');
    }
}