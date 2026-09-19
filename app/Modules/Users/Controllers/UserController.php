<?php

namespace App\Modules\Users\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Authentication\Resources\UserResource;
use App\Modules\Users\Models\User;
use App\Modules\Users\Requests\StoreUserRequest;
use App\Modules\Users\Requests\UpdateUserRequest;
use App\Modules\Users\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseController
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->userService->getAll($request->query());

        return $this->paginatedResponse($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->create($request->validated());

        activity('users')
            ->performedOn($user)
            ->event('created')
            ->withProperties($request->safe()->except(['password', 'password_confirmation', 'distributor']))
            ->log('User created');

        return $this->createdResponse(
            new UserResource($user),
            __('auth_messages.user_created_successfully')
        );
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->loadMissing('distributor.areas');

        return $this->resourceResponse(
            new UserResource($user),
            __('auth_messages.user_retrieved')
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->userService->update($user, $request->safe()->except(['password_confirmation']));

        activity('users')
            ->performedOn($user)
            ->event('updated')
            ->withProperties($request->safe()->except(['password', 'password_confirmation', 'distributor']))
            ->log('User updated');

        return $this->successResponse(
            new UserResource($user),
            __('auth_messages.user_updated_successfully')
        );
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->userService->delete($user);

        activity('users')
            ->performedOn($user)
            ->event('deleted')
            ->log('User deleted');

        return $this->noContentResponse(__('auth_messages.user_deleted_successfully'));
    }

    public function toggleStatus(User $user): JsonResponse
    {
        $this->authorize('toggleStatus', $user);

        $user = $this->userService->toggleStatus($user);

        activity('users')
            ->performedOn($user)
            ->event('status_toggled')
            ->withProperties(['is_active' => $user->is_active])
            ->log('User status toggled');

        return $this->successResponse(
            new UserResource($user),
            __('auth_messages.user_status_updated_successfully')
        );
    }
}
