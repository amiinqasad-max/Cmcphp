<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(MenuSeeder::class);

        $admin = User::firstOrCreate(
            ['email' => 'admin@cmcphp.test'],
            ['name' => 'Super Admin', 'password' => bcrypt('password')]
        );

        $admin->assignRole('super_admin');
    }
}
