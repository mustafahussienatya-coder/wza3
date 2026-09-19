<?php

namespace App\Modules\Roles\Services;

use App\Enums\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function getAll(): Collection
    {
        return Role::query()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                $role->system = $this->isSystemRole($role->name);

                return $role;
            });
    }

    public function updatePermissions(Role $role, array $permissionNames): Role
    {
        return DB::transaction(function () use ($role, $permissionNames) {
            $role->syncPermissions($permissionNames);

            return $role->fresh('permissions');
        });
    }

    public function allPermissions(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(function (Permission $permission) {
                return explode('.', $permission->name)[0] ?? 'general';
            })
            ->map(function (Collection $group, string $module) {
                return [
                    'module' => $module,
                    'permissions' => $group->map(fn (Permission $p) => [
                        'name' => $p->name,
                        'action' => explode('.', $p->name)[1] ?? 'view',
                        'label' => $p->name,
                    ])->values(),
                ];
            })
            ->values();
    }

    public function isSystemRole(string $name): bool
    {
        return UserRole::tryFrom($name) !== null;
    }
}
