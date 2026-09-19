<?php

use App\Modules\Categories\Models\Category;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Products\Models\Product;
use App\Modules\Units\Models\Unit;
use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createInventoryUser(string $role = 'admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Inventory User',
        'username' => 'inventory_user_'.$role.uniqid(),
        'email' => 'inventory_'.$role.'_'.uniqid().'@example.com',
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

function inventoryDataset(string $productName = 'سكر'): array
{
    $category = Category::create(['name' => 'مواد غذائية', 'code' => 'CAT-'.uniqid(), 'status' => 'active']);
    $kg = Unit::create(['name' => 'كيلوجرام_'.uniqid(), 'decimal_places' => 3, 'is_weight' => true]);
    $ton = Unit::create(['name' => 'طن_'.uniqid()]);

    $product = Product::create([
        'name' => $productName.'_'.uniqid(),
        'code' => 'PRD-'.strtoupper(uniqid()),
        'category_id' => $category->id,
        'base_unit_id' => $kg->id,
        'min_stock_level' => 50,
        'status' => 'active',
    ]);

    $product->units()->createMany([
        ['unit_id' => $kg->id, 'conversion_factor' => 1, 'selling_price' => 0, 'cost_price' => 0, 'is_active' => true],
        ['unit_id' => $ton->id, 'conversion_factor' => 1000, 'selling_price' => 0, 'cost_price' => 0, 'is_active' => true],
    ]);

    $warehouse = Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-'.uniqid(), 'status' => 'active']);
    $tonUnit = Unit::find($ton->id);

    return [
        'category' => $category,
        'product' => $product,
        'warehouse' => $warehouse,
        'baseUnit' => $kg,
        'tonUnit' => $tonUnit,
    ];
}

it('lets super admin add stock and creates inventory', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.type', 'stock_in')
        ->assertJsonPath('data.reason', 'purchase_order')
        ->assertJsonPath('data.quantity', '1000.0000')
        ->assertJsonPath('data.conversion_factor', '1000.0000')
        ->assertJsonPath('data.to_warehouse.name', $data['warehouse']->name);

    expect(StockMovement::count())->toBe(1)
        ->and(StockBatch::count())->toBe(1);

    $inventory = Inventory::query()
        ->where('product_id', $data['product']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();

    expect($inventory)->not->toBeNull()
        ->and((float) $inventory->quantity)->toBe(1000.0);

    $batch = StockBatch::query()
        ->where('stock_movement_id', StockMovement::first()->id)
        ->first();

    expect((float) $batch->remaining)->toBe(1000.0)
        ->and((float) $batch->unit_cost)->toBe(35.0);
});

it('lets admin with inventory.adjust add stock', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('admin', ['inventory.adjust']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 5,
        'unit_price' => 34000,
        'reason' => 'purchase_order',
    ])
        ->assertCreated()
        ->assertJsonPath('data.quantity', '5000.0000');
});

it('forbids a distributor from adding stock', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertForbidden();
});

it('rejects a non-positive quantity', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 0,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['quantity']]);
});

it('rejects an invalid reason', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'not_a_real_reason',
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['reason']]);
});

it('rejects a unit that does not belong to the product', function () {
    $data = inventoryDataset();
    $otherUnit = Unit::create(['name' => 'كيس_'.uniqid()]);
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $otherUnit->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['unit_id']]);
});

it('rejects an inactive product', function () {
    $data = inventoryDataset();
    $data['product']->update(['status' => 'inactive']);
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['product_id']]);
});

it('lists inventory overview with computed status', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('admin', ['inventory.view']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 100,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertCreated();

    $this->getJson('/api/v1/inventory')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'normal')
        ->assertJsonStructure(['data' => [['id', 'product', 'warehouse', 'quantity', 'status']], 'meta']);
});

it('marks inventory as out when quantity is zero', function () {
    $data = inventoryDataset();
    Inventory::create([
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'quantity' => 0,
    ]);

    $user = createInventoryUser('admin', ['inventory.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/inventory?status=out')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'out');
});

it('forbids listing inventory without inventory.view', function () {
    $role = Role::findByName('distributor');
    $role->revokePermissionTo('inventory.view');
    $user = createInventoryUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/inventory')->assertForbidden();
});

it('lets super admin correct an erroneous stock entry', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertCreated();

    $original = StockMovement::first();

    $this->postJson("/api/v1/inventory/movements/{$original->id}/correct")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.type', 'stock_out')
        ->assertJsonPath('data.reason', 'entry_error')
        ->assertJsonPath('data.reference_no', $original->movement_no)
        ->assertJsonPath('data.quantity', '1000.0000');

    $inventory = Inventory::query()
        ->where('product_id', $data['product']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();

    expect((float) $inventory->quantity)->toBe(0.0);

    $batch = StockBatch::query()->where('stock_movement_id', $original->id)->first();
    expect((float) $batch->remaining)->toBe(0.0)
        ->and(StockMovement::count())->toBe(2);
});

it('forbids a role without inventory.correct from correcting', function () {
    $data = inventoryDataset();
    $role = Role::findByName('distributor');
    $role->revokePermissionTo('inventory.correct');
    $role->revokePermissionTo('inventory.adjust');
    $role->revokePermissionTo('inventory.view');
    $user = createInventoryUser('distributor', ['inventory.adjust']);
    $user->givePermissionTo('inventory.view');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertCreated();

    $original = StockMovement::first();

    $this->postJson("/api/v1/inventory/movements/{$original->id}/correct")->assertForbidden();
});

it('allows administrator role to view stock on hand', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertCreated();

    $this->getJson('/api/v1/inventory/stock-on-hand')
        ->assertOk()
        ->assertJsonPath('data.0.product.id', $data['product']->id);
});

it('allows administrator role to correct a movement', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertCreated();

    $original = StockMovement::first();

    $this->postJson("/api/v1/inventory/movements/{$original->id}/correct")->assertOk();
});

it('forbids distributor role from viewing stock on hand and adjusting it', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/inventory/stock-on-hand')->assertForbidden();

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertForbidden();
});

it('forbids customer service role from viewing stock on hand and adjusting it', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('customer_service');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/inventory/stock-on-hand')->assertForbidden();

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertForbidden();
});

it('cannot correct the same movement twice', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertCreated();

    $original = StockMovement::first();
    $this->postJson("/api/v1/inventory/movements/{$original->id}/correct")->assertOk();

    $this->postJson("/api/v1/inventory/movements/{$original->id}/correct")
        ->assertJsonPath('error_code', 'CANNOT_CORRECT_MOVEMENT');
});

it('lists movements and filters by type', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'opening_balance',
    ])->assertCreated();

    $this->getJson('/api/v1/inventory/movements')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reason', 'opening_balance');

    $this->getJson('/api/v1/inventory/movements?type='.StockMovementType::STOCK_OUT->value)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('backfills distributor_id for legacy distributor movements', function () {
    $data = inventoryDataset();
    $distributorUser = User::create([
        'name' => 'الموزع محمود',
        'username' => 'dist_bf'.uniqid(),
        'email' => 'dist_bf_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => 'distributor',
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $distributor = Distributor::create([
        'user_id' => $distributorUser->id,
        'status' => 'active',
    ]);
    $issue = DistributorIssue::create([
        'issue_number' => 'DI-'.$distributor->id.'-'.$data['product']->id,
        'distributor_id' => $distributor->id,
        'warehouse_id' => $data['warehouse']->id,
        'status' => 'completed',
        'created_by' => $distributorUser->id,
    ]);
    $user = createInventoryUser('super_admin');

    StockMovement::create([
        'movement_no' => StockMovement::generateMovementNo(),
        'product_id' => $data['product']->id,
        'from_warehouse_id' => $data['warehouse']->id,
        'type' => StockMovementType::DISTRIBUTOR_ISSUE,
        'reason' => StockMovementReason::DISTRIBUTOR_ISSUE,
        'quantity' => '500.0000',
        'unit_id' => $data['baseUnit']->id,
        'conversion_factor' => '1',
        'unit_price' => null,
        'distributor_id' => null,
        'reference_type' => 'distributor_issue',
        'reference_no' => $issue->issue_number,
        'user_id' => $user->id,
        'moved_at' => now(),
    ]);

    DB::statement(<<<'SQL'
        UPDATE stock_movements
        SET distributor_id = (
            SELECT distributor_issues.distributor_id
            FROM distributor_issues
            WHERE distributor_issues.issue_number = stock_movements.reference_no
            LIMIT 1
        )
        WHERE stock_movements.distributor_id IS NULL
          AND stock_movements.reference_type IN ('distributor_issue', 'custody_return')
          AND stock_movements.reference_no IS NOT NULL
    SQL);

    expect((int) StockMovement::first()->distributor_id)
        ->toBe($distributor->id);
});

it('includes the distributor in the movements list for distributor issues', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('super_admin');
    $distributorUser = User::create([
        'name' => 'الموزع أحمد',
        'username' => 'dist_mov_'.uniqid(),
        'email' => 'dist_mov_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => 'distributor',
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $distributor = Distributor::create([
        'user_id' => $distributorUser->id,
        'status' => 'active',
    ]);
    Sanctum::actingAs($user, ['*']);

    StockMovement::create([
        'movement_no' => StockMovement::generateMovementNo(),
        'product_id' => $data['product']->id,
        'from_warehouse_id' => $data['warehouse']->id,
        'type' => StockMovementType::DISTRIBUTOR_ISSUE,
        'reason' => StockMovementReason::DISTRIBUTOR_ISSUE,
        'quantity' => '1000.0000',
        'unit_id' => $data['baseUnit']->id,
        'conversion_factor' => '1',
        'unit_price' => '3700.00',
        'distributor_id' => $distributor->id,
        'reference_type' => 'distributor_issue',
        'reference_no' => 'DI-1000',
        'user_id' => $user->id,
        'moved_at' => now(),
    ]);

    $this->getJson('/api/v1/inventory/movements')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.distributor.id', $distributor->id)
        ->assertJsonPath('data.0.distributor.name', 'الموزع أحمد');
});

it('allows administrator role to view stock movements', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'opening_balance',
    ])->assertCreated();

    $this->getJson('/api/v1/inventory/movements')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('forbids distributor role from viewing stock movements', function () {
    $user = createInventoryUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/inventory/movements')->assertForbidden();
});

it('forbids customer service role from viewing stock movements', function () {
    $user = createInventoryUser('customer_service');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/inventory/movements')->assertForbidden();
});

function fifoFixture(): array
{
    $data = inventoryDataset();
    $user = createInventoryUser('super_admin');

    $movement = StockMovement::create([
        'movement_no' => StockMovement::generateMovementNo(),
        'product_id' => $data['product']->id,
        'to_warehouse_id' => $data['warehouse']->id,
        'type' => StockMovementType::OPENING_BALANCE,
        'reason' => StockMovementReason::OPENING_BALANCE,
        'quantity' => '0',
        'unit_id' => $data['baseUnit']->id,
        'conversion_factor' => '1',
        'unit_price' => null,
        'user_id' => $user->id,
        'moved_at' => now(),
    ]);

    $data['movement'] = $movement;

    return $data;
}

function fifoBatch(array $data, string $batchNo, string $date, string $remaining, string $cost): StockBatch
{
    return StockBatch::create([
        'stock_movement_id' => $data['movement']->id,
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'batch_no' => $batchNo,
        'received_at' => $date,
        'quantity' => $remaining,
        'remaining' => $remaining,
        'unit_cost' => $cost,
    ]);
}

it('bulk adds stock for multiple rows atomically', function () {
    $dataOne = inventoryDataset('سكر');
    $dataTwo = inventoryDataset('أرز');

    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/bulk-stock-in', [
        'rows' => [
            [
                'product_id' => $dataOne['product']->id,
                'warehouse_id' => $dataOne['warehouse']->id,
                'unit_id' => $dataOne['tonUnit']->id,
                'quantity' => 2,
                'unit_price' => 35000,
                'reason' => 'purchase_order',
            ],
            [
                'product_id' => $dataTwo['product']->id,
                'warehouse_id' => $dataTwo['warehouse']->id,
                'unit_id' => $dataTwo['baseUnit']->id,
                'quantity' => 50,
                'unit_price' => 1500,
                'reason' => 'opening_balance',
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.quantity', '2000.0000')
        ->assertJsonPath('data.1.quantity', '50.0000');

    expect(StockMovement::count())->toBe(2);

    $inventoryOne = Inventory::query()
        ->where('product_id', $dataOne['product']->id)
        ->where('warehouse_id', $dataOne['warehouse']->id)
        ->first();

    $inventoryTwo = Inventory::query()
        ->where('product_id', $dataTwo['product']->id)
        ->where('warehouse_id', $dataTwo['warehouse']->id)
        ->first();

    expect((float) $inventoryOne->quantity)->toBe(2000.0)
        ->and((float) $inventoryTwo->quantity)->toBe(50.0);
});

it('rejects bulk stock-in without rows', function () {
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/bulk-stock-in', ['rows' => []])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['rows']]);
});

it('validates every bulk row', function () {
    $data = inventoryDataset();
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/bulk-stock-in', [
        'rows' => [
            [
                'product_id' => $data['product']->id,
                'warehouse_id' => $data['warehouse']->id,
                'unit_id' => $data['tonUnit']->id,
                'quantity' => 0,
                'unit_price' => 35000,
                'reason' => 'purchase_order',
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['rows.0.quantity']]);
});

it('exposes total_stock on the product listing', function () {
    $data = inventoryDataset('سكر');
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => 3,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertCreated();

    $this->getJson('/api/v1/products?search='.urlencode($data['product']->name))
        ->assertOk()
        ->assertJsonPath('data.0.total_stock', '3000.0000');
});

it('filters products by low_stock flag', function () {
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $out = inventoryDataset('ناقص_كامل');
    $low = inventoryDataset('ناقص_جزئي');
    $full = inventoryDataset('مخزن_كامل');
    $zeroMin = inventoryDataset('بدون_حد');

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $low['product']->id,
        'warehouse_id' => $low['warehouse']->id,
        'unit_id' => $low['tonUnit']->id,
        'quantity' => 0.03,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertCreated();

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $full['product']->id,
        'warehouse_id' => $full['warehouse']->id,
        'unit_id' => $full['tonUnit']->id,
        'quantity' => 1,
        'unit_price' => 35000,
        'reason' => 'purchase_order',
    ])->assertCreated();

    $zeroMin['product']->update(['min_stock_level' => 0]);

    $ids = collect($this->getJson('/api/v1/products?low_stock=1')->json('data'))->pluck('id')->all();
    expect($ids)->toContain($out['product']->id)
        ->toContain($low['product']->id)
        ->toContain($zeroMin['product']->id)
        ->not->toContain($full['product']->id);
});

it('fifo service allocates from the oldest batch first', function () {
    $data = fifoFixture();

    $batchOne = fifoBatch($data, 'B-TEST-1', '2026-01-01', '100', '10.00');
    $batchTwo = fifoBatch($data, 'B-TEST-2', '2026-01-10', '200', '12.00');

    $lines = (new FifoService)->allocate($data['product']->id, $data['warehouse']->id, '250');

    expect($lines)->toHaveCount(2)
        ->and($lines[0]['batch']->id)->toBe($batchOne->id)
        ->and((float) $lines[0]['quantity'])->toBe(100.0)
        ->and($lines[1]['batch']->id)->toBe($batchTwo->id)
        ->and((float) $lines[1]['quantity'])->toBe(150.0);
});

it('fifo service throws when stock is insufficient', function () {
    $data = fifoFixture();
    fifoBatch($data, 'B-TEST-1', '2026-01-01', '100', '10.00');

    (new FifoService)->allocate($data['product']->id, $data['warehouse']->id, '150');
})->throws(InsufficientStockException::class);

it('returns stock on hand grouped by product with totals and latest unit cost', function () {
    $data = inventoryDataset('أرصدة');
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['baseUnit']->id,
        'quantity' => '10',
        'unit_price' => '5',
        'reason' => 'purchase_order',
    ])->assertCreated();

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['baseUnit']->id,
        'quantity' => '20',
        'unit_price' => '10',
        'reason' => 'purchase_order',
    ])->assertCreated();

    $this->getJson('/api/v1/inventory/stock-on-hand')
        ->assertOk()
        ->assertJsonPath('data.0.product.id', $data['product']->id)
        ->assertJsonPath('data.0.total', '30.0000')
        ->assertJsonPath('data.0.quantities.0.quantity', '30.0000')
        ->assertJsonPath('data.0.latest_unit_cost', '10.00')
        ->assertJsonCount(2, 'data.0.units');
});

it('rejects unauthenticated access to stock on hand', function () {
    $this->getJson('/api/v1/inventory/stock-on-hand')->assertUnauthorized();
});

it('filters stock on hand by warehouse', function () {
    $firstWarehouse = inventoryDataset('مخزن_أول');
    $secondWarehouse = inventoryDataset('مخزن_ثانٍ');
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $firstWarehouse['product']->id,
        'warehouse_id' => $firstWarehouse['warehouse']->id,
        'unit_id' => $firstWarehouse['baseUnit']->id,
        'quantity' => '5',
        'unit_price' => '5',
        'reason' => 'purchase_order',
    ])->assertCreated();

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $secondWarehouse['product']->id,
        'warehouse_id' => $secondWarehouse['warehouse']->id,
        'unit_id' => $secondWarehouse['baseUnit']->id,
        'quantity' => '7',
        'unit_price' => '5',
        'reason' => 'purchase_order',
    ])->assertCreated();

    $response = $this->getJson('/api/v1/inventory/stock-on-hand?warehouse_id='.$firstWarehouse['warehouse']->id);

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product.id', $firstWarehouse['product']->id);
});

it('returns batches for a product in a warehouse ordered oldest first', function () {
    $data = inventoryDataset('دفعات');
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['baseUnit']->id,
        'quantity' => '10',
        'unit_price' => '5',
        'reason' => 'purchase_order',
    ])->assertCreated();

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['baseUnit']->id,
        'quantity' => '20',
        'unit_price' => '10',
        'reason' => 'purchase_order',
    ])->assertCreated();

    $this->getJson('/api/v1/inventory/batches?product_id='.$data['product']->id.'&warehouse_id='.$data['warehouse']->id)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.quantity', '10.0000')
        ->assertJsonPath('data.0.remaining', '10.0000')
        ->assertJsonPath('data.0.unit_cost', '5.00')
        ->assertJsonPath('data.1.unit_cost', '10.00');
});

it('validates product_id is required for batches', function () {
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/inventory/batches')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_id']);
});

it('rejects unauthenticated access to batches', function () {
    $this->getJson('/api/v1/inventory/batches?product_id=1')->assertUnauthorized();
});

it('preserves the full received timestamp on batches when moved_at is provided', function () {
    $data = inventoryDataset('وقت_دفعة');
    $user = createInventoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
        'product_id' => $data['product']->id,
        'warehouse_id' => $data['warehouse']->id,
        'unit_id' => $data['baseUnit']->id,
        'quantity' => '5',
        'unit_price' => '5',
        'reason' => 'purchase_order',
        'moved_at' => '2026-09-08T14:30:00Z',
    ])->assertCreated();

    $batch = StockBatch::first();

    expect($batch->received_at?->toIso8601String())->toBe('2026-09-08T14:30:00+00:00');
});
