<?php

namespace Database\Seeders;

use App\Modules\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@example.com', 'role' => 'super_admin'],
            ['name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                    'is_active' => true,
                    'must_change_password' => true,
                ]
            );
        }

        $this->call([
            RolesAndPermissionsSeeder::class,
            CategorySeeder::class,
            UnitsSeeder::class,
            AreasSeeder::class,
        ]);
    }
}
