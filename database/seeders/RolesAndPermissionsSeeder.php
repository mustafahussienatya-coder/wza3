<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Modules\Users\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [];
        $permissionSets = [];

        foreach (UserRole::cases() as $role) {
            $roles[] = $role;
            $permissionSets[$role->value] = $role->permissions();
        }

        $allPermissions = collect($permissionSets)
            ->flatten()
            ->unique()
            ->values();

        foreach ($allPermissions as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach ($roles as $roleEnum) {
            $role = Role::findOrCreate($roleEnum->value);
            $role->syncPermissions($permissionSets[$roleEnum->value]);
        }

        User::query()->each(function (User $user) {
            $roleEnum = UserRole::fromValue((string) $user->role);
            if ($roleEnum === null) {
                return;
            }

            $user->assignRole($roleEnum->value);
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
