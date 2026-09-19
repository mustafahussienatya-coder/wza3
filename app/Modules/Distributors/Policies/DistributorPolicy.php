<?php

namespace App\Modules\Distributors\Policies;

use App\Enums\UserRole;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Users\Models\User;

class DistributorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('distributors.view');
    }

    public function view(User $user, Distributor $distributor): bool
    {
        if ($user->can('distributors.view')) {
            return true;
        }

        return $user->id === $distributor->user_id;
    }

    public function updateStatus(User $user, Distributor $distributor): bool
    {
        return $user->can('distributors.update');
    }

    public function viewDocuments(User $user, Distributor $distributor): bool
    {
        if ($user->can('distributors.view')) {
            return true;
        }

        return $user->id === $distributor->user_id;
    }

    public function viewCustody(User $user, Distributor $distributor): bool
    {
        if (! $user->can('custody.view')) {
            return false;
        }

        if ($user->hasRole(UserRole::ADMIN->value)) {
            return true;
        }

        return $user->id === $distributor->user_id;
    }
}
