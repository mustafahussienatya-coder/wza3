<?php

namespace App\Modules\Customers\Policies;

use App\Enums\UserRole;
use App\Modules\Customers\Models\Customer;
use App\Modules\Users\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        if (! $user->can('customers.view')) {
            return false;
        }

        if ($user->hasRole(UserRole::DISTRIBUTOR->value)) {
            return $customer->distributor_id === $user->distributor?->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        if (! $user->can('customers.create')) {
            return false;
        }

        if ($user->hasRole(UserRole::DISTRIBUTOR->value)) {
            return $user->distributor !== null;
        }

        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        if (! $user->can('customers.update')) {
            return false;
        }

        if ($user->hasRole(UserRole::DISTRIBUTOR->value)) {
            return $customer->distributor_id === $user->distributor?->id;
        }

        return true;
    }

    public function delete(User $user, Customer $customer): bool
    {
        if (! $user->can('customers.delete')) {
            return false;
        }

        return ! $customer->ledgerEntries()->exists();
    }

    public function transfer(User $user, Customer $customer): bool
    {
        return $user->can('customers.transfer');
    }

    public function changeStatus(User $user, Customer $customer): bool
    {
        return $user->can('customers.change_status');
    }

    public function adjustOpeningBalance(User $user, Customer $customer): bool
    {
        return $user->can('customers.opening_balance');
    }

    public function viewLedger(User $user, Customer $customer): bool
    {
        if (! $user->can('customers.statement')) {
            return false;
        }

        if ($user->hasRole(UserRole::DISTRIBUTOR->value)) {
            return $customer->distributor_id === $user->distributor?->id;
        }

        return true;
    }

    public function viewSummary(User $user): bool
    {
        return $user->can('customers.view');
    }
}
