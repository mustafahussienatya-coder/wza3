<?php

namespace App\Modules\Invoices\Policies;

use App\Enums\UserRole;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Users\Models\User;

class InvoicePolicy
{
    private function scoped(User $user): ?int
    {
        return $user->hasRole(UserRole::DISTRIBUTOR->value) ? $user->distributor?->id : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.view')) {
            return false;
        }

        $distributorId = $this->scoped($user);

        return $distributorId === null || $invoice->sales_distributor_id === $distributorId;
    }

    public function create(User $user): bool
    {
        if (! $user->can('invoices.create')) {
            return false;
        }

        if ($user->hasRole(UserRole::DISTRIBUTOR->value)) {
            return $user->distributor !== null;
        }

        return true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.update')) {
            return false;
        }

        if (! $invoice->isDraft()) {
            return false;
        }

        $distributorId = $this->scoped($user);

        return $distributorId === null || $invoice->sales_distributor_id === $distributorId;
    }

    public function confirm(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.confirm')) {
            return false;
        }

        $distributorId = $this->scoped($user);

        return $distributorId === null || $invoice->sales_distributor_id === $distributorId;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        if (! $user->can('invoices.cancel')) {
            return false;
        }

        $distributorId = $this->scoped($user);

        return $distributorId === null || $invoice->sales_distributor_id === $distributorId;
    }
}
