<?php

use App\Modules\Categories\Models\Category;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Products\Models\Product;
use App\Modules\Units\Models\Unit;
use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function bulkProductsUser(string $role = 'admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Bulk Products User',
        'username' => 'bulk_products_'.$role.uniqid(),
        'email' => 'bulk_'.$role.'_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => $role,
        'is_active' => true,
        'must_change_password' => false,
    ]);

    $user->assignRole($role);

    if ($permissions) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function bulkProductsCategory(): Category
{
    return Category::create(['name' => 'مواد غذائية', 'code' => 'CAT-'.uniqid(), 'status' => 'active']);
}

function bulkProductsKgUnit(): Unit
{
    return Unit::create(['name' => 'كيلوجرام_'.uniqid(), 'decimal_places' => 3, 'is_weight' => true]);
}

function bulkProductsWarehouse(): Warehouse
{
    return Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-'.uniqid(), 'status' => 'active']);
}

it('creates multiple products in a single bulk request', function () {
    $category = bulkProductsCategory();
    $kg = bulkProductsKgUnit();

    $user = bulkProductsUser('admin', ['products.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products/bulk', [
        'items' => [
            ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $kg->id],
            ['name' => 'رز', 'category_id' => $category->id, 'base_unit_id' => $kg->id, 'min_stock_level' => 10],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.created_count', 2)
        ->assertJsonPath('data.errors_count', 0)
        ->assertJsonCount(2, 'data.created');

    expect(Product::count())->toBe(2);
});

it('rejects more than 300 items', function () {
    $user = bulkProductsUser('admin', ['products.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products/bulk', [
        'items' => array_fill(0, 301, ['name' => 'منتج']),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['items']]);
});

it('partially succeeds when one row is invalid', function () {
    $category = bulkProductsCategory();
    $kg = bulkProductsKgUnit();

    $user = bulkProductsUser('admin', ['products.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products/bulk', [
        'items' => [
            ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $kg->id],
            ['name' => '', 'category_id' => $category->id, 'base_unit_id' => $kg->id],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.created_count', 1)
        ->assertJsonPath('data.errors_count', 1)
        ->assertJsonPath('data.errors.0.error_code', 'NAME_REQUIRED')
        ->assertJsonStructure(['data' => ['errors' => [['index', 'name', 'error_code', 'message']]]]);

    expect(Product::count())->toBe(1);
});

it('rejects an invalid category and keeps the product out', function () {
    $kg = bulkProductsKgUnit();
    $inactive = Category::create(['name' => 'غير نشطة', 'code' => 'CAT-'.uniqid(), 'status' => 'inactive']);

    $user = bulkProductsUser('admin', ['products.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products/bulk', [
        'items' => [
            ['name' => 'سكر', 'category_id' => $inactive->id, 'base_unit_id' => $kg->id],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.created_count', 0)
        ->assertJsonPath('data.errors.0.error_code', 'CATEGORY_INVALID');

    expect(Product::count())->toBe(0);
});

it('rejects an opening balance row without a price and keeps the row atomic', function () {
    $category = bulkProductsCategory();
    $kg = bulkProductsKgUnit();
    $warehouse = bulkProductsWarehouse();

    $user = bulkProductsUser('admin', ['products.create', 'inventory.adjust']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products/bulk', [
        'items' => [
            ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $kg->id,
                'opening' => ['warehouse_id' => $warehouse->id, 'quantity' => 50]],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.created_count', 0)
        ->assertJsonPath('data.errors.0.error_code', 'OPENING_PRICE_REQUIRED');

    expect(Product::count())->toBe(0)
        ->and(Inventory::count())->toBe(0);
});

it('creates an opening balance batch and stock movement with opening_balance reason', function () {
    $category = bulkProductsCategory();
    $kg = bulkProductsKgUnit();
    $warehouse = bulkProductsWarehouse();

    $user = bulkProductsUser('admin', ['products.create', 'inventory.adjust']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products/bulk', [
        'items' => [
            ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $kg->id,
                'opening' => ['warehouse_id' => $warehouse->id, 'quantity' => 50, 'unit_price' => 10]],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.created_count', 1)
        ->assertJsonPath('data.errors_count', 0);

    $product = Product::first();

    $inventory = Inventory::where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect($inventory)->not->toBeNull()
        ->and((float) $inventory->quantity)->toBe(50.0);

    $movement = StockMovement::where('product_id', $product->id)->first();

    expect($movement)->not->toBeNull()
        ->and($movement->reason->value)->toBe('opening_balance')
        ->and((float) $movement->quantity)->toBe(50.0)
        ->and(StockBatch::where('stock_movement_id', $movement->id)->count())->toBe(1);
});

it('forbids bulk creation without products.create permission', function () {
    $user = bulkProductsUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products/bulk', [
        'items' => [['name' => 'سكر']],
    ])->assertForbidden();
});

it('requires inventory.adjust when an opening balance is present', function () {
    $category = bulkProductsCategory();
    $kg = bulkProductsKgUnit();
    $warehouse = bulkProductsWarehouse();

    $user = bulkProductsUser('customer_service', ['products.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products/bulk', [
        'items' => [
            ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $kg->id,
                'opening' => ['warehouse_id' => $warehouse->id, 'quantity' => 50, 'unit_price' => 10]],
        ],
    ])->assertForbidden();
});
