<?php

namespace App\Modules\Areas\Policies;

use App\Modules\Areas\Models\Area;
use App\Modules\Users\Models\User;

class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('areas.view');
    }

    public function view(User $user, Area $area): bool
    {
        return $user->can('areas.view');
    }

    public function create(User $user): bool
    {
        return $user->can('areas.create');
    }

    public function update(User $user, Area $area): bool
    {
        return $user->can('areas.update');
    }

    public function delete(User $user, Area $area): bool
    {
        return $user->can('areas.delete');
    }
}
