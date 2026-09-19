<?php

namespace App\Modules\Units\Policies;

use App\Modules\Units\Models\Unit;
use App\Modules\Users\Models\User;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('units.view');
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->can('units.view');
    }

    public function create(User $user): bool
    {
        return $user->can('units.create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->can('units.update');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->can('units.delete');
    }
}
