<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\AdminAssignableRolesSeeder;
use Database\Seeders\ComponentCategorySeeder;
use Database\Seeders\FabricSeeder;
use Database\Seeders\ColorSeeder;
use Database\Seeders\ComponentSeeder;
use Database\Seeders\SuppliersFromExcelSeeder;

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
            AdminAssignableRolesSeeder::class,
            ComponentCategorySeeder::class,
            FabricSeeder::class,
            ColorSeeder::class,
            ComponentSeeder::class,
            SuppliersFromExcelSeeder::class,
        ]);
    }
}
