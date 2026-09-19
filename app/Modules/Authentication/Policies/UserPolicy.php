<?php

namespace App\Modules\Authentication\Policies;

use App\Modules\Users\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        if ($user->can('users.view')) {
            if ($user->isDistributor() || $user->isCustomerService()) {
                return $user->id === $model->id;
            }

            return true;
        }

        return $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->can('users.create') && ! $user->isCustomerService();
    }

    public function update(User $user, User $model): bool
    {
        if (! $user->can('users.update')) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->can('users.delete')) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return true;
    }

    public function toggleStatus(User $user, User $model): bool
    {
        if (! $user->can('users.update')) {
            return false;
        }

        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $user->id !== $model->id;
    }
}
