<?php

namespace App\Modules\Distributors\Policies;

use App\Enums\UserRole;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Users\Models\User;

class DistributorIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('custody.view');
    }

    public function create(User $user): bool
    {
        return $user->can('custody.issue');
    }

    public function view(User $user, DistributorIssue $issue): bool
    {
        if (! $user->can('custody.view')) {
            return false;
        }

        if ($user->hasRole(UserRole::ADMIN->value)) {
            return true;
        }

        return $issue->distributor?->user_id === $user->id;
    }

    public function update(User $user, DistributorIssue $issue): bool
    {
        return $user->can('custody.issue');
    }

    public function issue(User $user, DistributorIssue $issue): bool
    {
        return $user->can('custody.issue');
    }

    public function disburse(User $user, DistributorIssue $issue): bool
    {
        return $user->can('custody.approve_disburse');
    }

    public function correct(User $user, DistributorIssue $issue): bool
    {
        return $user->can('custody.approve_disburse');
    }

    public function returnWarehouse(User $user, DistributorIssue $issue): bool
    {
        return $user->can('custody.approve_disburse');
    }
}
