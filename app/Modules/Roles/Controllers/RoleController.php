<?php

namespace App\Modules\Roles\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Roles\Requests\UpdateRolePermissionsRequest;
use App\Modules\Roles\Resources\RoleResource;
use App\Modules\Roles\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class RoleController extends BaseController
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('roles.view');

        $roles = $this->roleService->getAll();

        return $this->successResponse(
            RoleResource::collection($roles),
            __('roles.retrieved')
        );
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $this->authorize('roles.manage');

        $role = $this->roleService->updatePermissions($role, $request->validated()['permissions']);

        activity('roles')
            ->performedOn($role)
            ->event('updated')
            ->withProperties(['permissions' => $role->permissions->pluck('name')])
            ->log('Role permissions updated');

        return $this->successResponse(
            new RoleResource($role),
            __('roles.permissions_updated')
        );
    }
}
