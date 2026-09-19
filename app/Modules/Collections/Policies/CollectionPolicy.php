<?php

namespace App\Modules\Collections\Policies;

use App\Enums\UserRole;
use App\Modules\Collections\Models\Collection;
use App\Modules\Users\Models\User;

class CollectionPolicy
{
    private function scoped(User $user): ?int
    {
        return $user->hasRole(UserRole::DISTRIBUTOR->value) ? $user->distributor?->id : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Collection $collection): bool
    {
        if (! $user->can('payments.view')) {
            return false;
        }

        $distributorId = $this->scoped($user);

        return $distributorId === null || $collection->distributor_id === $distributorId;
    }

    public function create(User $user): bool
    {
        if (! $user->can('payments.create')) {
            return false;
        }

        if ($user->hasRole(UserRole::DISTRIBUTOR->value)) {
            return $user->distributor !== null;
        }

        return true;
    }
}
