<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Users\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, Inventory $inventory): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.adjust');
    }

    public function correct(User $user, StockMovement $stockMovement): bool
    {
        return $user->can('inventory.correct');
    }

    public function viewMovements(User $user): bool
    {
        return $user->can('inventory.movements');
    }

    public function viewStockCount(User $user): bool
    {
        return $user->can('inventory.count');
    }
}
