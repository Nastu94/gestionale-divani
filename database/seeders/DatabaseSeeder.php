<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
    {
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
            AdminUserSeeder::class,
            ComponentCategorySeeder::class,
            ComponentSeeder::class,
        ]);
    }
}
