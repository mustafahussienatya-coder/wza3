<?php

namespace App\Providers;

use App\Modules\Areas\Models\Area;
use App\Modules\Areas\Policies\AreaPolicy;
use App\Modules\Authentication\Policies\UserPolicy;
use App\Modules\Categories\Models\Category;
use App\Modules\Categories\Policies\CategoryPolicy;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Policies\CollectionPolicy;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Policies\CustomerPolicy;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Distributors\Policies\DistributorIssuePolicy;
use App\Modules\Distributors\Policies\DistributorPolicy;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Policies\InventoryPolicy;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Policies\InvoicePolicy;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Policies\ProductPolicy;
use App\Modules\Settlements\Models\DistributorSettlement;
use App\Modules\Settlements\Policies\SettlementPolicy;
use App\Modules\Units\Models\Unit;
use App\Modules\Units\Policies\UnitPolicy;
use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use App\Modules\Warehouses\Policies\WarehousePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Area::class => AreaPolicy::class,
        Category::class => CategoryPolicy::class,
        Unit::class => UnitPolicy::class,
        Customer::class => CustomerPolicy::class,
        Product::class => ProductPolicy::class,
        Warehouse::class => WarehousePolicy::class,
        Distributor::class => DistributorPolicy::class,
        DistributorIssue::class => DistributorIssuePolicy::class,
        Inventory::class => InventoryPolicy::class,
        StockMovement::class => InventoryPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Collection::class => CollectionPolicy::class,
        DistributorSettlement::class => SettlementPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerPermissionGates();
    }

    private function registerPermissionGates(): void
    {
        Gate::before(function (User $user) {
            return $user->isSuperAdmin() ? true : null;
        });

        try {
            Permission::get()->each(function (Permission $permission) {
                Gate::define($permission->name, fn (User $user) => $user->hasRole($permission->roles));
            });
        } catch (\Throwable $e) {
            return;
        }
    }
}
