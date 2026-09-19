<?php

namespace App\Modules\Settlements\Policies;

use App\Enums\UserRole;
use App\Modules\Settlements\Models\DistributorSettlement;
use App\Modules\Users\Models\User;

class SettlementPolicy
{
    private function scoped(User $user): ?int
    {
        return $user->hasRole(UserRole::DISTRIBUTOR->value) ? $user->distributor?->id : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('settlements.view');
    }

    public function view(User $user, DistributorSettlement $settlement): bool
    {
        if (! $user->can('settlements.view')) {
            return false;
        }

        $distributorId = $this->scoped($user);

        return $distributorId === null || $settlement->distributor_id === $distributorId;
    }

    public function create(User $user): bool
    {
        if (! $user->can('settlements.create')) {
            return false;
        }

        return true;
    }
}
