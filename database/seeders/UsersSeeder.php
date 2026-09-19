<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\Distributors\Models\Distributor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'email' => 'admin@example.com',
                'password' => 'P@ssw0rd!',
                'role' => UserRole::SUPER_ADMIN,
                'must_change_password' => false,
            ],
            [
                'name' => 'Admin Officer',
                'username' => 'adminofficer',
                'email' => 'adminofficer@example.com',
                'password' => 'P@ssw0rd!',
                'role' => UserRole::ADMIN,
                'must_change_password' => true,
            ],
            [
                'name' => 'Customer Service',
                'username' => 'customerservice',
                'email' => 'service@example.com',
                'password' => 'P@ssw0rd!',
                'role' => UserRole::CUSTOMER_SERVICE,
                'must_change_password' => true,
            ],
            [
                'name' => 'Distributor User',
                'username' => 'distributor',
                'email' => 'distributor@example.com',
                'password' => 'P@ssw0rd!',
                'role' => UserRole::DISTRIBUTOR,
                'must_change_password' => true,
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make($data['password']),
                    'role' => $data['role']->value,
                    'is_active' => true,
                    'must_change_password' => $data['must_change_password'],
                ]
            );
        }

        $distributorUser = User::where('email', 'distributor@example.com')->first();

        if ($distributorUser !== null) {
            Distributor::updateOrCreate(
                ['user_id' => $distributorUser->id],
                ['status' => 'active']
            );
        }
    }
}
