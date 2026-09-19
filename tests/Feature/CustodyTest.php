<?php

use App\Modules\Categories\Models\Category;
use App\Modules\Distributors\Actions\CompleteDistributorIssueAction;
use App\Modules\Distributors\Actions\CorrectDistributorIssueAction;
use App\Modules\Distributors\Actions\ReturnDistributorIssueAction;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Distributors\Models\DistributorIssueCorrection;
use App\Modules\Distributors\Models\DistributorIssueCorrectionItem;
use App\Modules\Distributors\Models\DistributorIssueItem;
use App\Modules\Inventory\Actions\AddStockInAction;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementLine;
use App\Modules\Products\Models\Product;
use App\Modules\Units\Models\Unit;
use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createCustodyUser(string $role = 'super_admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Custody User',
        'username' => 'custody_'.$role.uniqid(),
        'email' => 'custody_'.$role.'_'.uniqid().'@example.com',
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

function custodyDataset(string $productName = 'سكر'): array
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

    return [
        'category' => $category,
        'product' => $product,
        'warehouse' => $warehouse,
        'distributor' => makeDistributor(),
        'baseUnit' => $kg,
        'tonUnit' => $ton,
    ];
}

function stockCustodyWarehouse(array $data, string $quantity = '1000'): void
{
    app(AddStockInAction::class)->execute(
        productId: $data['product']->id,
        warehouseId: $data['warehouse']->id,
        unitId: $data['baseUnit']->id,
        quantity: $quantity,
        unitPrice: '0',
        reason: StockMovementReason::OPENING_BALANCE,
        type: StockMovementType::OPENING_BALANCE,
        userId: $data['distributor']->user->id,
    );
}

function custodyIssuePayload(array $data, array $overrides = []): array
{
    return array_merge([
        'distributor_id' => $data['distributor']->id,
        'warehouse_id' => $data['warehouse']->id,
        'notes' => 'تسليم عهدة أولية',
        'items' => [
            ['product_id' => $data['product']->id, 'unit_id' => $data['tonUnit']->id, 'quantity' => 1, 'unit_price' => '0'],
        ],
    ], $overrides);
}

function makeCustodyIssue(array $data, User $user, string $status = 'pending_approval'): DistributorIssue
{
    $issue = DistributorIssue::create([
        'issue_number' => DistributorIssue::generateIssueNumber(),
        'distributor_id' => $data['distributor']->id,
        'warehouse_id' => $data['warehouse']->id,
        'status' => $status,
        'created_by' => $user->id,
        'notes' => 'إيصال اختبار',
    ]);

    $issue->items()->create([
        'product_id' => $data['product']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => '1',
        'base_quantity' => '1000',
        'conversion_factor' => '1000',
    ]);

    return $issue;
}

it('creates a draft issue with sequential number and items', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.issue_number', 'DI-00001')
        ->assertJsonPath('data.distributor.name', $data['distributor']->user->name)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.base_quantity', '1000.0000');

    expect(DistributorIssue::count())->toBe(1)
        ->and(DistributorIssueItem::count())->toBe(1);
});

it('increments the issue number for the second issue', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data))->assertCreated();
    $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data))
        ->assertCreated()
        ->assertJsonPath('data.issue_number', 'DI-00002');
});

it('forbids creating an issue without custody.issue permission', function () {
    $data = custodyDataset();
    $user = createCustodyUser('customer_service');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data))->assertForbidden();
});

it('rejects an issue item with non positive quantity', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['tonUnit']->id,
            'quantity' => 0,
        ]],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['items.0.quantity']]);
});

it('submits a draft issue for approval', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $issue = DistributorIssue::find(
        $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data))
            ->assertCreated()
            ->json('data.id')
    );

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_approval');
});

it('rejects submitting an issue that is not a draft', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/submit")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_ISSUE_STATUS_TRANSITION');
});

it('approves a pending issue when warehouse stock is enough', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '2000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approved_by.name', $user->name);
});

it('rejects approving an issue that is not pending approval', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'draft');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_ISSUE_STATUS_TRANSITION');
});

it('rejects approval when warehouse stock is insufficient', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INSUFFICIENT_WAREHOUSE_STOCK');
});

it('completes an approved issue, decrements stock and creates custody ledger entries', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'approved');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.completed_by.name', $user->name);

    $inventory = Inventory::query()
        ->where('product_id', $data['product']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();

    expect((float) $inventory->quantity)->toBe(0.0);

    $snapshot = DistributorInventory::query()
        ->where('distributor_id', $data['distributor']->id)
        ->where('product_id', $data['product']->id)
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and((float) $snapshot->quantity)->toBe(1000.0);

    $movement = CustodyMovement::first();

    expect(CustodyMovement::count())->toBe(1)
        ->and($movement->movement_type->value)->toBe('issue')
        ->and((float) $movement->base_quantity)->toBe(1000.0)
        ->and($movement->reference_type)->toBe('distributor_issue')
        ->and($movement->reference_id)->toBe($issue->id)
        ->and((int) $movement->performed_by)->toBe($user->id);
});

it('rejects completing an issue that is not approved', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/complete")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_ISSUE_STATUS_TRANSITION');
});

it('rolls back nothing when completing fails due to insufficient stock', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '100');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'approved');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/complete")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INSUFFICIENT_WAREHOUSE_STOCK');

    expect(CustodyMovement::count())->toBe(0)
        ->and(DistributorInventory::count())->toBe(0)
        ->and(StockMovement::where('type', 'distributor_issue')->count())->toBe(0)
        ->and((float) Inventory::query()->where('product_id', $data['product']->id)->first()->quantity)->toBe(100.0);

    $this->assertDatabaseHas('distributor_issues', ['id' => $issue->id, 'status' => 'approved']);
});

it('cancels a draft issue and then refuses a second cancel', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'draft');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/cancel")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_ISSUE_STATUS_TRANSITION');
});

it('lets a distributor view their own custody but not another distributor', function () {
    $data = custodyDataset();
    $other = makeDistributor();
    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/custody')->assertOk();
    $this->getJson('/api/v1/distributors/'.$other->id.'/custody')->assertForbidden();
});

it('forbids customer service from viewing custody', function () {
    $data = custodyDataset();
    $user = createCustodyUser('customer_service');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/custody')->assertForbidden();
});

it('lists issues and filters by status', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data))->assertCreated();
    $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data, ['notes' => 'ثاني']))
        ->assertCreated()
        ->json('data.id');
});

it('updates a draft issue and replaces its items', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'draft');
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/distributor-issues/'.$issue->id, custodyIssuePayload($data, [
        'notes' => 'ملاحظة محدثة',
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['baseUnit']->id,
            'quantity' => 5,
            'unit_price' => '20',
        ]],
    ]))
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.notes', 'ملاحظة محدثة')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.base_quantity', '5.0000');
});

it('refuses updating a non-draft issue', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->putJson('/api/v1/distributor-issues/'.$issue->id, custodyIssuePayload($data))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_ISSUE_STATUS_TRANSITION');
});

it('returns the custody statement with a running balance', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'approved');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/complete")->assertOk();

    $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/custody/statement')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.movement_type', 'issue')
        ->assertJsonPath('data.0.base_quantity', '1000.0000')
        ->assertJsonPath('data.0.running_base_quantity', '1000.0000')
        ->assertJsonPath('data.0.reference_number', $issue->issue_number);
});

it('searches the custody statement by issue number', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'approved');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/complete")->assertOk();

    $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/custody/statement?search='.urlencode($issue->issue_number))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference_number', $issue->issue_number);
});

it('orders the custody statement newest first', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '3000');
    $user = createCustodyUser('super_admin');
    $first = makeCustodyIssue($data, $user, 'approved');
    $second = makeCustodyIssue($data, $user, 'approved');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$first->id}/complete")->assertOk();
    $this->postJson("/api/v1/distributor-issues/{$second->id}/complete")->assertOk();

    $movements = CustodyMovement::where('distributor_id', $data['distributor']->id)->orderBy('id')->get();
    expect($movements)->toHaveCount(2);

    $movements[0]->forceFill(['created_at' => '2025-01-01 10:00:00'])->save();
    $movements[1]->forceFill(['created_at' => '2025-01-02 10:00:00'])->save();

    $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/custody/statement')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.reference_number', $second->issue_number)
        ->assertJsonPath('data.0.running_base_quantity', '2000.0000')
        ->assertJsonPath('data.1.reference_number', $first->issue_number)
        ->assertJsonPath('data.1.running_base_quantity', '1000.0000');
});

it('lets a distributor see only their own issues', function () {
    $data = custodyDataset();
    $other = custodyDataset();
    $super = createCustodyUser('super_admin');
    $ownIssue = makeCustodyIssue($data, $super, 'pending_approval');
    makeCustodyIssue($other, $super, 'pending_approval');

    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $rows = $this->getJson('/api/v1/distributor-issues')->assertOk()->json('data');

    expect(count($rows))->toBe(1)
        ->and($rows[0]['id'])->toBe($ownIssue->id);
});

it('returns list issues through the issue resource', function () {
    $data = custodyDataset();
    $admin = createCustodyUser('super_admin');
    makeCustodyIssue($data, $admin, 'pending_approval');

    Sanctum::actingAs($admin, ['*']);

    $rows = $this->getJson('/api/v1/distributor-issues')->assertOk()->json('data');

    expect(count($rows))->toBe(1)
        ->and($rows[0])
        ->toHaveKey('issue_number')
        ->toHaveKey('status', 'pending_approval')
        ->toHaveKey('status_label', 'Pending Approval')
        ->toHaveKey('status_label_ar', 'بانتظار الاعتماد')
        ->toHaveKey('distributor.name', $data['distributor']->user->name)
        ->toHaveKey('warehouse.name', $data['warehouse']->name)
        ->toHaveKey('items');
});

it('returns a localized forbidden response when a distributor tries to approve', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '2000');
    $admin = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $admin, 'pending_approval');

    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve", [], ['X-Locale' => 'ar'])
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'FORBIDDEN')
        ->assertJsonPath('message', 'غير مصرح لك بتنفيذ هذا الإجراء.');
});

it('returns the custody inventory balance for the distributor', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'approved');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/complete")->assertOk();

    $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/custody')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.quantity', '1000.0000')
        ->assertJsonPath('data.0.product.name', $data['product']->name);
});

it('stores an editable unit price on issue items', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['tonUnit']->id,
            'quantity' => 1,
            'unit_price' => '125.50',
        ]],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.items.0.unit_price', '125.50');

    expect((string) DistributorIssueItem::first()->unit_price)->toBe('125.50');
});

it('rejects an issue item with a negative unit price', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['tonUnit']->id,
            'quantity' => 1,
            'unit_price' => -5,
        ]],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['items.0.unit_price']]);
});

it('approves and disburses a pending issue in one step', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve-disburse")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.approved_by.name', $user->name)
        ->assertJsonPath('data.completed_by.name', $user->name);

    $inventory = Inventory::query()
        ->where('product_id', $data['product']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();

    expect((float) $inventory->quantity)->toBe(0.0);

    $snapshot = DistributorInventory::query()
        ->where('distributor_id', $data['distributor']->id)
        ->where('product_id', $data['product']->id)
        ->first();

    expect($snapshot)->not->toBeNull()
        ->and((float) $snapshot->quantity)->toBe(1000.0)
        ->and(CustodyMovement::count())->toBe(1);

    $movement = StockMovement::query()
        ->where('type', 'distributor_issue')
        ->first();

    expect($movement)->not->toBeNull()
        ->and(bccomp((string) $movement->quantity, '1000.0000', 4))->toBe(0)
        ->and($movement->reference_no)->toBe($issue->issue_number)
        ->and((string) $movement->from_warehouse_id)->toBe((string) $data['warehouse']->id)
        ->and((string) $movement->distributor_id)->toBe((string) $data['distributor']->id)
        ->and((int) $movement->user_id)->toBe($user->id);

    $line = StockMovementLine::first();

    expect(StockMovementLine::count())->toBe(1)
        ->and((float) $line->quantity)->toBe(1000.0)
        ->and((float) $line->stockBatch->remaining)->toBe(0.0);
});

it('empties the custody batches when a completed issue is returned to the warehouse', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'approved');

    app(CompleteDistributorIssueAction::class)->execute($issue, $user->id);

    $batch = CustodyBatch::query()->firstOrFail();

    expect((float) $batch->remaining)->toBe(1000.0)
        ->and(CustodyMovement::count())->toBe(1);

    app(ReturnDistributorIssueAction::class)->execute($issue->refresh(), $user->id);

    expect((float) $batch->refresh()->remaining)->toBe(0.0)
        ->and(CustodyBatch::query()->where('remaining', '>', 0)->count())->toBe(0);
});

it('adjusts the custody batch when a correction lowers the issued quantity', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '2000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'approved');

    app(CompleteDistributorIssueAction::class)->execute($issue, $user->id);

    $item = $issue->items()->firstOrFail();

    app(CorrectDistributorIssueAction::class)->execute($issue, [
        'items' => [
            ['id' => $item->id, 'quantity' => '0.5', 'unit_price' => '150.00'],
        ],
    ], $user->id);

    expect((float) CustodyBatch::query()->sum('remaining'))->toBe(500.0)
        ->and((float) CustodyBatch::query()->sum('quantity'))->toBe(1500.0);
});

it('consumes the oldest batches first (FIFO) when disbursing', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '400');
    stockCustodyWarehouse($data, '800');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve-disburse")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $batches = StockBatch::query()
        ->where('product_id', $data['product']->id)
        ->orderBy('id')
        ->get();

    expect((float) $batches[0]->remaining)->toBe(0.0)
        ->and((float) $batches[1]->remaining)->toBe(200.0);

    $movement = StockMovement::query()
        ->where('type', 'distributor_issue')
        ->first();

    expect($movement->lines)->toHaveCount(2)
        ->and((float) $movement->lines[0]->quantity)->toBe(400.0)
        ->and((float) $movement->lines[1]->quantity)->toBe(600.0);

    $inventory = Inventory::query()
        ->where('product_id', $data['product']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();

    expect((float) $inventory->quantity)->toBe(200.0);
});

it('forbids non-admin users from approving and disbursing an issue', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $admin = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $admin, 'pending_approval');

    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $this->postJson(
        "/api/v1/distributor-issues/{$issue->id}/approve-disburse",
        [],
        ['X-Locale' => 'ar']
    )
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'FORBIDDEN')
        ->assertJsonPath('message', 'غير مصرح لك بتنفيذ هذا الإجراء.');
});

it('forbids approving and disbursing without the custody.approve_disburse permission', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $super = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $super, 'pending_approval');

    Role::findByName('admin', 'web')->revokePermissionTo('custody.approve_disburse');

    $admin = createCustodyUser('admin');
    Sanctum::actingAs($admin, ['*']);

    $this->postJson(
        "/api/v1/distributor-issues/{$issue->id}/approve-disburse",
        [],
        ['X-Locale' => 'ar']
    )
        ->assertForbidden();
});

it('allows approving and disbursing when the admin role is granted custody.approve_disburse', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $super = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $super, 'pending_approval');

    Role::findByName('admin')->givePermissionTo('custody.approve_disburse');

    $admin = createCustodyUser('admin');
    Sanctum::actingAs($admin, ['*']);

    $this->postJson(
        "/api/v1/distributor-issues/{$issue->id}/approve-disburse",
        [],
        ['X-Locale' => 'ar']
    )
        ->assertOk();
});

it('rejects approve-disburse when warehouse stock is insufficient', function () {
    $data = custodyDataset();
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve-disburse")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INSUFFICIENT_WAREHOUSE_STOCK');

    expect(CustodyMovement::count())->toBe(0)
        ->and(DistributorInventory::count())->toBe(0);
});

it('corrects item quantities and prices on a completed issue', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '2000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve-disburse")
        ->assertOk();

    $itemId = $issue->items()->first()->id;

    $this->putJson("/api/v1/distributor-issues/{$issue->id}/correct", [
        'items' => [
            ['id' => $itemId, 'quantity' => '2', 'unit_price' => '150.00'],
        ],
    ], ['X-Locale' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonCount(1, 'data.corrections');

    $updated = DistributorIssueItem::findOrFail($itemId);

    expect((float) $updated->quantity)->toBe(1.0)
        ->and((float) $updated->base_quantity)->toBe(1000.0)
        ->and((float) $updated->unit_price)->toBe(0.0);

    $correction = DistributorIssueCorrection::first();

    expect($correction)->not->toBeNull()
        ->and($correction->correction_no)->toBe('C-00001')
        ->and($correction->status->value)->toBe('completed');

    $correctionItem = DistributorIssueCorrectionItem::first();

    expect((float) $correctionItem->corrected_quantity)->toBe(2.0)
        ->and((float) $correctionItem->corrected_base_quantity)->toBe(2000.0)
        ->and((float) $correctionItem->corrected_unit_price)->toBe(150.0)
        ->and($correctionItem->correction_type->value)->toBe('mixed');

    $warehouseInventory = Inventory::query()
        ->where('product_id', $data['product']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();

    expect((float) $warehouseInventory->quantity)->toBe(0.0);

    $snapshot = DistributorInventory::query()
        ->where('distributor_id', $data['distributor']->id)
        ->where('product_id', $data['product']->id)
        ->first();

    expect((float) $snapshot->quantity)->toBe(2000.0);

    $movements = CustodyMovement::query()->orderBy('id')->get();

    expect($movements)->toHaveCount(4)
        ->and($movements[0]->movement_type->value)->toBe('issue')
        ->and((float) $movements[0]->selling_price)->toBe(0.0)
        ->and($movements[1]->movement_type->value)->toBe('issue')
        ->and((float) $movements[1]->selling_price)->toBe(150.0)
        ->and($movements[2]->movement_type->value)->toBe('return_to_warehouse')
        ->and($movements[3]->movement_type->value)->toBe('issue')
        ->and((float) $movements[3]->selling_price)->toBe(150.0);

    expect(StockMovement::where('type', 'distributor_issue')->count())->toBe(3)
        ->and(StockMovement::where('type', 'custody_return')->count())->toBe(1);
});

it('returns the corrected quantity difference to the warehouse when quantities decrease', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '2000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve-disburse")
        ->assertOk();

    $itemId = $issue->items()->first()->id;

    $this->putJson("/api/v1/distributor-issues/{$issue->id}/correct", [
        'items' => [
            ['id' => $itemId, 'quantity' => '0.5', 'unit_price' => '150.00'],
        ],
    ], ['X-Locale' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $updated = DistributorIssueItem::findOrFail($itemId);

    expect((float) $updated->quantity)->toBe(1.0)
        ->and((float) $updated->base_quantity)->toBe(1000.0)
        ->and((float) $updated->unit_price)->toBe(0.0);

    $correctionItem = DistributorIssueCorrectionItem::first();

    expect((float) $correctionItem->corrected_quantity)->toBe(0.5)
        ->and((float) $correctionItem->corrected_base_quantity)->toBe(500.0)
        ->and((float) $correctionItem->corrected_unit_price)->toBe(150.0);

    $warehouseInventory = Inventory::query()
        ->where('product_id', $data['product']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();

    expect((float) $warehouseInventory->quantity)->toBe(1500.0);

    $snapshot = DistributorInventory::query()
        ->where('distributor_id', $data['distributor']->id)
        ->where('product_id', $data['product']->id)
        ->first();

    expect((float) $snapshot->quantity)->toBe(500.0);

    $returnMovements = StockMovement::query()
        ->where('type', 'custody_return')
        ->orderBy('id')
        ->get();

    expect($returnMovements)->toHaveCount(2)
        ->and((float) $returnMovements->first()->quantity)->toBe(500.0);

    $movements = CustodyMovement::query()->orderBy('id')->get();

    expect($movements)->toHaveCount(4)
        ->and($movements[1]->movement_type->value)->toBe('return_to_warehouse')
        ->and((float) $movements[1]->base_quantity)->toBe(500.0)
        ->and($movements[2]->movement_type->value)->toBe('return_to_warehouse')
        ->and($movements[3]->movement_type->value)->toBe('issue');
});

it('changes only the selling price by returning and reissuing the remaining quantity', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '2000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve-disburse")
        ->assertOk();

    $itemId = $issue->items()->first()->id;

    $this->putJson("/api/v1/distributor-issues/{$issue->id}/correct", [
        'items' => [
            ['id' => $itemId, 'unit_price' => '150.00'],
        ],
    ], ['X-Locale' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $updated = DistributorIssueItem::findOrFail($itemId);

    expect((float) $updated->quantity)->toBe(1.0)
        ->and((float) $updated->base_quantity)->toBe(1000.0)
        ->and((float) $updated->unit_price)->toBe(0.0);

    $correctionItem = DistributorIssueCorrectionItem::first();

    expect($correctionItem->correction_type->value)->toBe('price')
        ->and((float) $correctionItem->corrected_quantity)->toBe(1.0)
        ->and((float) $correctionItem->corrected_unit_price)->toBe(150.0);

    $warehouseInventory = Inventory::query()
        ->where('product_id', $data['product']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();

    expect((float) $warehouseInventory->quantity)->toBe(1000.0);

    $snapshot = DistributorInventory::query()
        ->where('distributor_id', $data['distributor']->id)
        ->where('product_id', $data['product']->id)
        ->first();

    expect((float) $snapshot->quantity)->toBe(1000.0);

    expect(StockMovement::where('type', 'distributor_issue')->count())->toBe(2)
        ->and(StockMovement::where('type', 'custody_return')->count())->toBe(1);

    $movements = CustodyMovement::query()->orderBy('id')->get();

    expect($movements)->toHaveCount(3)
        ->and($movements[1]->movement_type->value)->toBe('return_to_warehouse')
        ->and((float) $movements[1]->selling_price)->toBe(0.0)
        ->and($movements[2]->movement_type->value)->toBe('issue')
        ->and((float) $movements[2]->selling_price)->toBe(150.0);
});

it('forbids correcting issues without the custody.approve_disburse permission', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '2000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve-disburse")
        ->assertOk();

    $itemId = $issue->items()->first()->id;

    Role::findByName('admin', 'web')->revokePermissionTo('custody.approve_disburse');

    $admin = createCustodyUser('admin');
    Sanctum::actingAs($admin, ['*']);

    $this->putJson("/api/v1/distributor-issues/{$issue->id}/correct", [
        'items' => [
            ['id' => $itemId, 'unit_price' => '150.00'],
        ],
    ], ['X-Locale' => 'ar'])
        ->assertForbidden();
});

it('rejects correcting a non-completed issue', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '2000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $itemId = $issue->items()->first()->id;

    $this->putJson("/api/v1/distributor-issues/{$issue->id}/correct", [
        'items' => [
            ['id' => $itemId, 'quantity' => '2', 'unit_price' => '150.00'],
        ],
    ], ['X-Locale' => 'ar'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_ISSUE_STATUS_TRANSITION');
});

it('rejects correction when warehouse stock is insufficient for the quantity increase', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $user = createCustodyUser('super_admin');
    $issue = makeCustodyIssue($data, $user, 'pending_approval');
    Sanctum::actingAs($user, ['*']);

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve-disburse")
        ->assertOk();

    $itemId = $issue->items()->first()->id;

    $this->putJson("/api/v1/distributor-issues/{$issue->id}/correct", [
        'items' => [
            ['id' => $itemId, 'quantity' => '2', 'unit_price' => '150.00'],
        ],
    ], ['X-Locale' => 'ar'])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INSUFFICIENT_WAREHOUSE_STOCK');
});

it('returns one product tracker row per custody batch', function () {
    $data = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $user = createCustodyUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $issueId = $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data, [
        'items' => [
            ['product_id' => $data['product']->id, 'unit_id' => $data['tonUnit']->id, 'quantity' => 1, 'unit_price' => '125.50'],
        ],
    ]))->assertCreated()->json('data.id');

    $this->postJson("/api/v1/distributor-issues/{$issueId}/submit")->assertOk();
    $this->postJson("/api/v1/distributor-issues/{$issueId}/approve")->assertOk();
    $this->postJson("/api/v1/distributor-issues/{$issueId}/complete")->assertOk();

    $batch = CustodyBatch::firstOrFail();
    $issue = DistributorIssue::findOrFail($issueId);

    $response = $this->getJson('/api/v1/reports/custody/product-tracker')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.custody_batch_id', $batch->id)
        ->assertJsonPath('data.0.batch_no', $batch->batch_no)
        ->assertJsonPath('data.0.issue_id', $issue->id)
        ->assertJsonPath('data.0.issue_number', $issue->issue_number)
        ->assertJsonPath('data.0.distributor_id', $data['distributor']->id)
        ->assertJsonPath('data.0.distributor_name', $data['distributor']->user->name)
        ->assertJsonPath('data.0.product_id', $data['product']->id)
        ->assertJsonPath('data.0.product_name', $data['product']->name)
        ->assertJsonPath('data.0.product_code', $data['product']->code)
        ->assertJsonPath('data.0.unit', $data['baseUnit']->name)
        ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total']]);

    expect((float) $response->json('data.0.quantity'))->toBe(1000.0)
        ->and((float) $response->json('data.0.remaining'))->toBe(1000.0)
        ->and((float) $response->json('data.0.sold_quantity'))->toBe(0.0)
        ->and((float) $response->json('data.0.unit_price'))->toBe(125.5)
        ->and((float) $response->json('data.0.entry_value'))->toBe(125500.0)
        ->and($response->json('data.0.issued_at'))->not->toBeNull();
});

it('filters the product tracker report by distributor', function () {
    $data = custodyDataset();
    $other = custodyDataset();
    stockCustodyWarehouse($data, '1000');
    $user = createCustodyUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $issueId = $this->postJson('/api/v1/distributor-issues', custodyIssuePayload($data))
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/distributor-issues/{$issueId}/submit")->assertOk();
    $this->postJson("/api/v1/distributor-issues/{$issueId}/approve")->assertOk();
    $this->postJson("/api/v1/distributor-issues/{$issueId}/complete")->assertOk();

    $this->getJson('/api/v1/reports/custody/product-tracker?distributor_id='.$data['distributor']->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/reports/custody/product-tracker?distributor_id='.$other['distributor']->id)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('forbids the product tracker report without the custody.report permission', function () {
    custodyDataset();
    $user = createCustodyUser('customer_service');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/reports/custody/product-tracker')->assertForbidden();
});
