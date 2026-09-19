<?php

namespace App\Modules\Categories\Policies;

use App\Modules\Categories\Models\Category;
use App\Modules\Users\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories.delete');
    }
}
