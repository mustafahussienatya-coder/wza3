<?php

use App\Modules\Categories\Models\Category;
use App\Modules\Collections\Models\Collection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Inventory\Actions\AddStockInAction;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Invoices\Models\Invoice;
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

function makeMyPanelDistributor(array $userData = []): Distributor
{
    $user = User::create([
        'name' => $userData['name'] ?? 'الموزع فلان',
        'username' => 'dist_'.uniqid(),
        'email' => $userData['email'] ?? 'dist_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => 'distributor',
        'is_active' => $userData['is_active'] ?? true,
        'must_change_password' => false,
    ]);
    $user->assignRole('distributor');

    return Distributor::create([
        'user_id' => $user->id,
        'status' => $userData['status'] ?? 'active',
    ]);
}

function myPanelDataset(): array
{
    $category = Category::create(['name' => 'مواد غذائية', 'code' => 'CAT-'.uniqid(), 'status' => 'active']);
    $kg = Unit::create(['name' => 'كيلوجرام_'.uniqid(), 'decimal_places' => 3, 'is_weight' => true]);

    $product = Product::create([
        'name' => 'سكر_'.uniqid(),
        'code' => 'PRD-'.strtoupper(uniqid()),
        'category_id' => $category->id,
        'base_unit_id' => $kg->id,
        'min_stock_level' => 50,
        'status' => 'active',
    ]);

    $product->units()->create([
        'unit_id' => $kg->id,
        'conversion_factor' => 1,
        'selling_price' => 25,
        'cost_price' => 20,
        'is_active' => true,
    ]);

    $warehouse = Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-'.uniqid(), 'status' => 'active']);

    return compact('category', 'product', 'warehouse', 'kg');
}

function fundMyPanelCustody(array $data, Distributor $distributor): void
{
    app(AddStockInAction::class)->execute(
        productId: $data['product']->id,
        warehouseId: $data['warehouse']->id,
        unitId: $data['kg']->id,
        quantity: '1000',
        unitPrice: '20',
        reason: StockMovementReason::OPENING_BALANCE,
        type: StockMovementType::OPENING_BALANCE,
        userId: $distributor->user_id,
    );

    $issue = DistributorIssue::create([
        'issue_number' => DistributorIssue::generateIssueNumber(),
        'distributor_id' => $distributor->id,
        'warehouse_id' => $data['warehouse']->id,
        'status' => 'completed',
        'created_by' => $distributor->user_id,
    ]);

    $issue->items()->create([
        'product_id' => $data['product']->id,
        'unit_id' => $data['kg']->id,
        'quantity' => '1000',
        'base_quantity' => '1000',
        'conversion_factor' => '1',
        'unit_price' => '25',
    ]);

    DistributorInventory::create([
        'distributor_id' => $distributor->id,
        'product_id' => $data['product']->id,
        'quantity' => '1000',
    ]);

    $movement = CustodyMovement::create([
        'distributor_id' => $distributor->id,
        'product_id' => $data['product']->id,
        'movement_type' => 'issue',
        'quantity' => '1000',
        'unit_id' => $data['kg']->id,
        'base_quantity' => '1000',
        'conversion_factor' => '1',
        'selling_price' => '25',
        'reference_type' => 'distributor_issue',
        'reference_id' => $issue->id,
        'performed_by' => $distributor->user_id,
        'created_at' => now(),
    ]);

    CustodyBatch::create([
        'distributor_id' => $distributor->id,
        'product_id' => $data['product']->id,
        'source_issue_id' => $issue->id,
        'source_movement_id' => $movement->id,
        'batch_no' => CustodyBatch::generateBatchNo($distributor->id, $data['product']->id),
        'issued_at' => now(),
        'quantity' => '1000',
        'remaining' => '1000',
        'unit_price' => '25',
    ]);
}

it('lets a distributor fetch sellable custody products', function () {
    $data = myPanelDataset();
    $distributor = makeMyPanelDistributor();
    fundMyPanelCustody($data, $distributor);
    Sanctum::actingAs($distributor->user, ['*']);

    $this->getJson('/api/v1/distributor/my/custody/sellable')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $data['product']->id)
        ->assertJsonPath('data.0.available_quantity', '1000.0000')
        ->assertJsonCount(1, 'data.0.units')
        ->assertJsonPath('data.0.units.0.unit_id', $data['kg']->id);
});

it('lets a distributor fetch their dashboard stats and recent movements', function () {
    $data = myPanelDataset();
    $distributor = makeMyPanelDistributor();
    fundMyPanelCustody($data, $distributor);
    Sanctum::actingAs($distributor->user, ['*']);

    $response = $this->getJson('/api/v1/distributor/my/dashboard')
        ->assertOk()
        ->assertJsonPath('success', true);

    $stats = $response->json('data.stats');
    $movements = $response->json('data.recent_movements');

    expect($stats)
        ->toHaveKey('custody.total_quantity', '1000.0000')
        ->toHaveKey('custody.items_count', 1)
        ->toHaveKey('custody.estimated_value', '25000.00')
        ->toHaveKey('sales_total', '0.00')
        ->toHaveKey('collected_amount', '0.00')
        ->toHaveKey('collected_unsettled', '0.00')
        ->toHaveKey('total_responsibility', '25000.00')
        ->toHaveKey('overdue_amount', '0.00');

    expect($movements)->toHaveCount(1)
        ->and($movements[0])->toHaveKey('type', 'issue')
        ->toHaveKey('product_name', $data['product']->name);
});

it('computes real sales, collected, overdue amounts and customers count', function () {
    $data = myPanelDataset();
    $distributor = makeMyPanelDistributor();
    $customer = Customer::create([
        'code' => 'CUS-'.uniqid(),
        'name' => 'عميل التوزيع',
        'phone' => '01000000000',
        'area_id' => null,
        'distributor_id' => $distributor->id,
        'created_by' => $distributor->user_id,
        'credit_limit' => '0',
        'status' => 'active',
    ]);
    Customer::create([
        'code' => 'CUS-'.uniqid(),
        'name' => 'عميل آخر',
        'phone' => '01000000001',
        'area_id' => null,
        'distributor_id' => $distributor->id,
        'created_by' => $distributor->user_id,
        'credit_limit' => '0',
        'status' => 'active',
    ]);

    Invoice::create([
        'invoice_number' => 'INV-'.uniqid(),
        'invoice_type' => 'sales',
        'sales_distributor_id' => $distributor->id,
        'customer_id' => $customer->id,
        'subtotal' => '10000',
        'discount_total' => '0',
        'total_amount' => '10000',
        'invoice_date' => now(),
        'status' => 'confirmed',
        'is_active' => true,
        'created_by' => $distributor->user_id,
        'warehouse_id' => $data['warehouse']->id,
    ]);

    Collection::create([
        'collection_number' => 'PY-'.uniqid(),
        'distributor_id' => $distributor->id,
        'customer_id' => $customer->id,
        'amount' => '4000',
        'payment_method' => 'cash',
        'collection_date' => now(),
        'created_by' => $distributor->user_id,
    ]);

    Sanctum::actingAs($distributor->user, ['*']);

    $stats = $this->getJson('/api/v1/distributor/my/dashboard')
        ->assertOk()
        ->json('data.stats');

    expect($stats)
        ->toHaveKey('sales_total', '10000.00')
        ->toHaveKey('collected_amount', '4000.00')
        ->toHaveKey('collected_unsettled', '4000.00')
        ->toHaveKey('total_responsibility', '4000.00')
        ->toHaveKey('overdue_amount', '6000.00')
        ->toHaveKey('customers_count', 2);
});

it('lets a distributor fetch their own custody inventory', function () {
    $data = myPanelDataset();
    $distributor = makeMyPanelDistributor();
    fundMyPanelCustody($data, $distributor);
    Sanctum::actingAs($distributor->user, ['*']);

    $this->getJson('/api/v1/distributor/my/custody')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.quantity', '1000.0000')
        ->assertJsonPath('data.0.product.name', $data['product']->name);
});

it('lets a distributor fetch their own custody statement', function () {
    $data = myPanelDataset();
    $distributor = makeMyPanelDistributor();
    fundMyPanelCustody($data, $distributor);
    Sanctum::actingAs($distributor->user, ['*']);

    $this->getJson('/api/v1/distributor/my/custody/statement')
        ->assertOk()
        ->assertJsonPath('data.0.movement_type', 'issue');
});

it('forbids a non-distributor role from the my panel endpoints', function () {
    $user = User::create([
        'name' => 'Admin User',
        'username' => 'admin_'.uniqid(),
        'email' => 'admin_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => 'admin',
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $user->assignRole('admin');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/distributor/my/dashboard')->assertForbidden();
    $this->getJson('/api/v1/distributor/my/custody')->assertForbidden();
    $this->getJson('/api/v1/distributor/my/custody/statement')->assertForbidden();
});

it('forbids a distributor without a linked distributor record', function () {
    $user = User::create([
        'name' => 'No Linked Distributor',
        'username' => 'unlinked_'.uniqid(),
        'email' => 'unlinked_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => 'distributor',
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $user->assignRole('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/distributor/my/dashboard', ['X-Locale' => 'ar'])
        ->assertForbidden()
        ->assertJsonPath('error_code', 'DISTRIBUTOR_NOT_LINKED')
        ->assertJsonPath('message', 'حسابك غير مرتبط بسجل موزع.');
});

it('forbids a suspended distributor from the my panel', function () {
    $distributor = makeMyPanelDistributor(['status' => 'suspended']);
    Sanctum::actingAs($distributor->user, ['*']);

    $this->getJson('/api/v1/distributor/my/dashboard', ['X-Locale' => 'ar'])
        ->assertForbidden()
        ->assertJsonPath('error_code', 'DISTRIBUTOR_SUSPENDED')
        ->assertJsonPath('message', 'تم إيقاف حساب الموزع الخاص بك.');
});

it('forbids an inactive user from the my panel endpoints', function () {
    $distributor = makeMyPanelDistributor(['is_active' => false]);
    Sanctum::actingAs($distributor->user, ['*']);

    $this->getJson('/api/v1/distributor/my/dashboard')
        ->assertForbidden()
        ->assertJsonPath('error_code', 'ACCOUNT_DEACTIVATED');
});
