<?php

namespace App\Modules\Roles\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Roles\Services\RoleService;
use Illuminate\Http\JsonResponse;

class PermissionController extends BaseController
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('permissions.view');

        $permissions = $this->roleService->allPermissions();

        return $this->successResponse($permissions, __('permissions.retrieved'));
    }
}
