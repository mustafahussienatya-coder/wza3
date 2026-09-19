<?php

use App\Modules\Categories\Models\Category;
use App\Modules\Inventory\Models\Inventory;
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

function createProductUser(string $role = 'admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Product User',
        'username' => 'product_user_'.$role.uniqid(),
        'email' => 'product_'.$role.'_'.uniqid().'@example.com',
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

function productCategory(): Category
{
    return Category::create(['name' => 'مواد غذائية', 'code' => 'CAT-'.uniqid(), 'status' => 'active']);
}

function productKgUnit(): Unit
{
    return Unit::create(['name' => 'كيلوجرام_'.uniqid(), 'decimal_places' => 3, 'is_weight' => true]);
}

function productTonUnit(): Unit
{
    return Unit::create(['name' => 'طن_'.uniqid()]);
}

it('lets super admin create a product with multiple selling units', function () {
    $category = productCategory();
    $kg = productKgUnit();
    $ton = productTonUnit();

    $user = createProductUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products', [
        'name' => 'سكر',
        'category_id' => $category->id,
        'base_unit_id' => $kg->id,
        'min_stock_level' => 50,
        'units' => [
            ['unit_id' => $ton->id, 'conversion_factor' => 1000, 'selling_price' => 39000, 'cost_price' => 35000],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'سكر')
        ->assertJsonCount(2, 'data.units')
        ->assertJsonStructure(['data' => ['id', 'code', 'base_unit', 'status', 'units']]);
});

it('auto adds base unit row with factor 1 when not provided', function () {
    $category = productCategory();
    $kg = productKgUnit();
    $ton = productTonUnit();

    $user = createProductUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products', [
        'name' => 'رز',
        'category_id' => $category->id,
        'base_unit_id' => $kg->id,
        'units' => [
            ['unit_id' => $ton->id, 'conversion_factor' => 1000, 'selling_price' => 39000, 'cost_price' => 35000],
        ],
    ])
        ->assertCreated();

    $units = $this->get('/api/v1/products/1')->json('data.units');
    $base = collect($units)->firstWhere('unit_id', $kg->id);
    expect($base)->not->toBeNull()
        ->and($base['conversion_factor'])->toBe('1.0000')
        ->and($base['selling_price'])->toBe('0.00');
});

it('allows duplicate product names while codes stay unique', function () {
    $category = productCategory();
    $kg = productKgUnit();

    $user = createProductUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products', ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $kg->id])
        ->assertCreated();

    $this->postJson('/api/v1/products', ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $kg->id])
        ->assertCreated()
        ->assertJsonPath('data.name', 'سكر');
});

it('rejects a product without a name', function () {
    $category = productCategory();
    $kg = productKgUnit();

    $user = createProductUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products', ['category_id' => $category->id, 'base_unit_id' => $kg->id])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['name']]);
});

it('rejects an inactive category', function () {
    $category = Category::create(['name' => 'مواد محذوفة', 'code' => 'CAT-'.uniqid(), 'status' => 'inactive']);
    $kg = productKgUnit();

    $user = createProductUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products', ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $kg->id])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['category_id']]);
});

it('rejects an inactive base unit', function () {
    $category = productCategory();
    $inactiveKg = Unit::create(['name' => 'كيلوجرام', 'is_active' => false]);

    $user = createProductUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products', ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $inactiveKg->id])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['base_unit_id']]);
});

it('rejects a conversion factor of zero or below', function () {
    $category = productCategory();
    $kg = productKgUnit();
    $ton = productTonUnit();

    $user = createProductUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products', [
        'name' => 'سكر',
        'category_id' => $category->id,
        'base_unit_id' => $kg->id,
        'units' => [
            ['unit_id' => $ton->id, 'conversion_factor' => 0, 'selling_price' => 100, 'cost_price' => 80],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['units.0.conversion_factor']]);
});

it('rejects a duplicate barcode on a different product', function () {
    $category = productCategory();
    $kg = productKgUnit();

    $user = createProductUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $payload = [
        'name' => 'سكر',
        'category_id' => $category->id,
        'base_unit_id' => $kg->id,
        'units' => [
            ['unit_id' => $kg->id, 'conversion_factor' => 1, 'selling_price' => 40, 'cost_price' => 35, 'barcode' => '8801111'],
        ],
    ];

    $this->postJson('/api/v1/products', $payload)->assertCreated();

    $this->postJson('/api/v1/products', $payload)
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['units.0.barcode']]);
});

it('forbids a user without products.create permission', function () {
    $category = productCategory();
    $kg = productKgUnit();

    $user = createProductUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/products', ['name' => 'سكر', 'category_id' => $category->id, 'base_unit_id' => $kg->id])
        ->assertForbidden();
});

it('lists products for a user with products.view', function () {
    $category = productCategory();
    $kg = productKgUnit();
    Product::create(['name' => 'سكر', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id]);
    Product::create(['name' => 'أرز', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id]);

    $user = createProductUser('admin', ['products.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'code', 'status']], 'meta']);
});

it('filters products by category and status', function () {
    $categoryA = productCategory();
    $categoryB = Category::create(['name' => 'ألبان', 'code' => 'CAT-'.uniqid(), 'status' => 'active']);
    $kg = productKgUnit();

    Product::create(['name' => 'سكر', 'code' => Product::generateCode(), 'category_id' => $categoryA->id, 'base_unit_id' => $kg->id]);
    Product::create(['name' => 'حليب', 'code' => Product::generateCode(), 'category_id' => $categoryB->id, 'base_unit_id' => $kg->id]);
    Product::create(['name' => 'منتج موقوف', 'code' => Product::generateCode(), 'category_id' => $categoryA->id, 'base_unit_id' => $kg->id, 'status' => 'inactive']);

    $user = createProductUser('admin', ['products.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/products?category_id='.$categoryA->id)
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson('/api/v1/products?category_id='.$categoryA->id.'&status=active')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'سكر');
});

it('shows a single product with its selling units', function () {
    $category = productCategory();
    $kg = productKgUnit();
    $ton = productTonUnit();

    $product = Product::create(['name' => 'سكر', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id]);
    $product->units()->create(['unit_id' => $kg->id, 'conversion_factor' => 1, 'selling_price' => 40, 'cost_price' => 35]);
    $product->units()->create(['unit_id' => $ton->id, 'conversion_factor' => 1000, 'selling_price' => 39000, 'cost_price' => 35000]);

    $user = createProductUser('admin', ['products.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'سكر')
        ->assertJsonCount(2, 'data.units')
        ->assertJsonStructure(['data' => ['base_unit' => ['id', 'name'], 'units' => [['unit_name', 'conversion_factor', 'selling_price', 'cost_price']]]]);
});

it('updates a product and replaces its selling units', function () {
    $category = productCategory();
    $kg = productKgUnit();
    $ton = productTonUnit();

    $product = Product::create(['name' => 'سكر', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id]);
    $product->units()->create(['unit_id' => $kg->id, 'conversion_factor' => 1, 'selling_price' => 40, 'cost_price' => 35]);
    $product->units()->create(['unit_id' => $ton->id, 'conversion_factor' => 1000, 'selling_price' => 39000, 'cost_price' => 35000]);

    $user = createProductUser('admin', ['products.update']);
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/products/{$product->id}", [
        'name' => 'سكر ناعم',
        'category_id' => $category->id,
        'min_stock_level' => 100,
        'units' => [
            ['unit_id' => $kg->id, 'conversion_factor' => 1, 'selling_price' => 42, 'cost_price' => 36],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'سكر ناعم')
        ->assertJsonPath('data.min_stock_level', '100.000')
        ->assertJsonCount(1, 'data.units');
});

it('forbids changing the base unit on update', function () {
    $category = productCategory();
    $kg = productKgUnit();
    $gallon = Unit::create(['name' => 'جالون_'.uniqid()]);

    $product = Product::create(['name' => 'سكر', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id]);
    $product->units()->create(['unit_id' => $kg->id, 'conversion_factor' => 1, 'selling_price' => 40, 'cost_price' => 35]);

    $user = createProductUser('admin', ['products.update']);
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/products/{$product->id}", ['base_unit_id' => $gallon->id])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['base_unit_id']]);
});

it('deletes a product and cascades its units', function () {
    $category = productCategory();
    $kg = productKgUnit();

    $product = Product::create(['name' => 'سكر', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id]);
    $product->units()->create(['unit_id' => $kg->id, 'conversion_factor' => 1, 'selling_price' => 40, 'cost_price' => 35]);

    $user = createProductUser('admin', ['products.delete']);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/products/{$product->id}")
        ->assertNoContent();

    expect(Product::find($product->id))->toBeNull()
        ->and($product->units()->count())->toBe(0);
});

it('forbids deleting a product without permission', function () {
    $category = productCategory();
    $kg = productKgUnit();

    $product = Product::create(['name' => 'سكر', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id]);

    $user = createProductUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/products/{$product->id}")->assertForbidden();
});

it('searches products by unit barcode', function () {
    $category = productCategory();
    $kg = productKgUnit();

    $withBarcode = Product::create(['name' => 'سكر', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id]);
    $withBarcode->units()->create(['unit_id' => $kg->id, 'conversion_factor' => 1, 'barcode' => '6250001234567', 'selling_price' => 40, 'cost_price' => 35]);

    $withoutBarcode = Product::create(['name' => 'أرز', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id]);
    $withoutBarcode->units()->create(['unit_id' => $kg->id, 'conversion_factor' => 1, 'selling_price' => 30, 'cost_price' => 25]);

    $user = createProductUser('admin', ['products.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/products?search=6250001234567')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'سكر');
});

it('filters products by stock status', function () {
    $category = productCategory();
    $kg = productKgUnit();
    $warehouse = Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-'.uniqid(), 'status' => 'active']);

    $out = Product::create(['name' => 'نافد', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id, 'min_stock_level' => 50]);
    Inventory::create(['product_id' => $out->id, 'warehouse_id' => $warehouse->id, 'quantity' => 0]);

    $low = Product::create(['name' => 'منخفض', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id, 'min_stock_level' => 50]);
    Inventory::create(['product_id' => $low->id, 'warehouse_id' => $warehouse->id, 'quantity' => 20]);

    $normal = Product::create(['name' => 'متوفر', 'code' => Product::generateCode(), 'category_id' => $category->id, 'base_unit_id' => $kg->id, 'min_stock_level' => 50]);
    Inventory::create(['product_id' => $normal->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);

    $user = createProductUser('admin', ['products.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/products?stock_status=out')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'نافد');

    $this->getJson('/api/v1/products?stock_status=low')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'منخفض');

    $this->getJson('/api/v1/products?stock_status=normal')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'متوفر');
});
