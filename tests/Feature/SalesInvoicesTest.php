<?php

use App\Enums\UserRole;
use App\Modules\Categories\Models\Category;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerLedgerEntry;
use App\Modules\Distributors\Actions\CompleteDistributorIssueAction;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Distributors\Models\DistributorIssueItem;
use App\Modules\Inventory\Actions\AddStockInAction;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceItem;
use App\Modules\Invoices\Models\InvoiceItemAllocation;
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

if (! function_exists('makeDistributor')) {
    function makeDistributor(array $userData = []): Distributor
    {
        $user = User::create([
            'name' => $userData['name'] ?? 'الموزع فلان',
            'username' => 'dist_'.uniqid(),
            'email' => $userData['email'] ?? 'dist_'.uniqid().'@example.com',
            'password' => 'P@ssw0rd!',
            'role' => UserRole::DISTRIBUTOR->value,
            'is_active' => $userData['is_active'] ?? true,
            'must_change_password' => false,
            'phone' => $userData['phone'] ?? null,
        ]);
        $user->assignRole(UserRole::DISTRIBUTOR->value);

        return Distributor::create([
            'user_id' => $user->id,
            'status' => $userData['status'] ?? 'active',
        ]);
    }
}

if (! function_exists('custodyDataset')) {
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
}

if (! function_exists('stockCustodyWarehouse')) {
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
}

function createSalesAdmin(string $role = 'super_admin'): User
{
    return User::create([
        'name' => 'Sales Admin',
        'username' => 'sales_admin_'.$role.uniqid(),
        'email' => 'sales_admin_'.$role.'_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => $role,
        'is_active' => true,
        'must_change_password' => false,
    ])->assignRole($role);
}

function makeCustomer(?Distributor $distributor = null, array $overrides = []): Customer
{
    return Customer::create(array_merge([
        'code' => 'CUS-'.strtoupper(uniqid()),
        'name' => 'عميل بيع_'.uniqid(),
        'phone' => '01000000000',
        'distributor_id' => $distributor?->id,
        'credit_limit' => '100000',
        'status' => 'active',
        'notes' => null,
    ], $overrides));
}

function salesDataset(): array
{
    $data = custodyDataset('سلع بيع');
    $data['customer'] = makeCustomer($data['distributor']);
    $data['companyCustomer'] = makeCustomer();

    return $data;
}

function stockCompany(array $data, string $quantity = '1000', string $unitPrice = '100', ?int $userId = null): void
{
    app(AddStockInAction::class)->execute(
        productId: $data['product']->id,
        warehouseId: $data['warehouse']->id,
        unitId: $data['baseUnit']->id,
        quantity: $quantity,
        unitPrice: $unitPrice,
        reason: StockMovementReason::PURCHASE_ORDER,
        type: StockMovementType::STOCK_IN,
        userId: $userId ?? createSalesAdmin()->id,
    );
}

function companyInvoicePayload(array $data, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $data['companyCustomer']->id,
        'warehouse_id' => $data['warehouse']->id,
        'invoice_date' => '2026-09-17',
        'confirm' => false,
        'items' => [
            ['product_id' => $data['product']->id, 'unit_id' => $data['baseUnit']->id, 'quantity' => '250', 'unit_price' => '125.50'],
        ],
    ], $overrides);
}

function distributorInvoicePayload(array $data, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $data['customer']->id,
        'invoice_date' => '2026-09-17',
        'confirm' => false,
        'items' => [
            ['product_id' => $data['product']->id, 'unit_id' => $data['baseUnit']->id, 'quantity' => '250'],
        ],
    ], $overrides);
}

function makePricedApprovedIssue(array $data, User $user, string $unitPrice = '125.50'): DistributorIssue
{
    $issue = DistributorIssue::create([
        'issue_number' => DistributorIssue::generateIssueNumber(),
        'distributor_id' => $data['distributor']->id,
        'warehouse_id' => $data['warehouse']->id,
        'status' => 'approved',
        'created_by' => $user->id,
        'notes' => 'عهدة بيع',
    ]);

    DistributorIssueItem::create([
        'distributor_issue_id' => $issue->id,
        'product_id' => $data['product']->id,
        'unit_id' => $data['tonUnit']->id,
        'quantity' => '1',
        'base_quantity' => '1000',
        'conversion_factor' => '1000',
        'unit_price' => $unitPrice,
    ]);

    return $issue;
}

function disbursePricedCustody(array $data, User $user, string $unitPrice = '125.50', string $qty = '1000'): void
{
    stockCustodyWarehouse($data, $qty);
    $issue = makePricedApprovedIssue($data, $user, $unitPrice);
    app(CompleteDistributorIssueAction::class)->execute($issue, $user->id);
}

it('creates a company draft invoice with a sequential number', function () {
    $data = salesDataset();
    stockCompany($data);
    $user = createSalesAdmin();

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/invoices', companyInvoicePayload($data))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.invoice_number', 'C-000001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.ownership', 'company')
        ->assertJsonPath('data.total_amount', '31375.00')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.base_quantity', '250.0000');
});

it('increments the company invoice number', function () {
    $data = salesDataset();
    stockCompany($data);
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/invoices', companyInvoicePayload($data))->assertCreated();
    $this->postJson('/api/v1/invoices', companyInvoicePayload($data))
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'C-000002');
});

it('forbids creating an invoice without invoices.create permission', function () {
    $data = salesDataset();
    stockCompany($data);
    $user = createSalesAdmin('customer_service');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/invoices', companyInvoicePayload($data))->assertForbidden();
});

it('rejects a distributor invoice carrying a warehouse_id', function () {
    $data = salesDataset();
    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/invoices', distributorInvoicePayload($data, [
        'warehouse_id' => $data['warehouse']->id,
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['warehouse_id']]);
});

it('rejects invoicing a customer that is not owned by the distributor', function () {
    $data = salesDataset();
    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/invoices', distributorInvoicePayload($data, [
        'customer_id' => $data['companyCustomer']->id,
    ]))
        ->assertForbidden()
        ->assertJsonPath('error_code', 'CUSTOMER_NOT_OWNED');
});

it('rejects invoicing an inactive customer', function () {
    $data = salesDataset();
    stockCompany($data);
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/invoices', companyInvoicePayload($data, [
        'customer_id' => makeCustomer(overrides: ['status' => 'inactive'])->id,
    ]))
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'CUSTOMER_SUSPENDED');
});

it('returns print data for a company invoice', function () {
    $data = salesDataset();
    stockCompany($data, '400', '100');
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', companyInvoicePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['baseUnit']->id,
            'quantity' => '300',
            'unit_price' => '100.00',
        ]],
    ]))
        ->assertCreated()
        ->json('data.id');

    $this->postJson("/api/v1/invoices/{$id}/confirm")->assertOk();

    $this->getJson("/api/v1/invoices/{$id}/print")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.invoice_number', 'C-000001')
        ->assertJsonPath('data.customer.name', $data['companyCustomer']->name)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product_name', $data['product']->name)
        ->assertJsonPath('data.total_amount', '30000.00');
});

it('returns print data for a distributor-owned invoice via the my panel', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/distributor/my/invoices', distributorInvoicePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['baseUnit']->id,
            'quantity' => '200',
            'unit_price' => '110.00',
        ]],
    ]))
        ->assertCreated()
        ->json('data.id');

    $this->getJson("/api/v1/distributor/my/invoices/{$id}/print")
        ->assertOk()
        ->assertJsonPath('data.ownership', 'distributor')
        ->assertJsonPath('data.sales_distributor.name', $data['distributor']->user->name)
        ->assertJsonCount(1, 'data.items');
});

it('resolves an invoice reference id on the warehouse ledger for sale movements', function () {
    $data = salesDataset();
    stockCompany($data, '400', '100');
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', companyInvoicePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['baseUnit']->id,
            'quantity' => '300',
            'unit_price' => '100.00',
        ]],
    ]))
        ->assertCreated()
        ->json('data.id');

    $this->postJson("/api/v1/invoices/{$id}/confirm")->assertOk();

    $this->getJson('/api/v1/inventory/movements')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'sale')
        ->assertJsonPath('data.0.reference_no', 'C-000001')
        ->assertJsonPath('data.0.invoice_reference_id', $id);
});

it('confirms a company invoice, consumes FIFO batches and writes the ledger', function () {
    $data = salesDataset();
    stockCompany($data, '400', '100');
    stockCompany($data, '800', '80');
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', companyInvoicePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['baseUnit']->id,
            'quantity' => '600',
            'unit_price' => '125.50',
        ]],
    ]))
        ->assertCreated()
        ->json('data.id');

    $this->postJson("/api/v1/invoices/{$id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.total_amount', '75300.00');

    $batches = StockBatch::query()->orderBy('id')->get();

    expect((float) $batches[0]->remaining)->toBe(0.0)
        ->and((float) $batches[1]->remaining)->toBe(600.0);

    $inventory = Inventory::query()
        ->where('product_id', $data['product']->id)
        ->where('warehouse_id', $data['warehouse']->id)
        ->first();

    expect((float) $inventory->quantity)->toBe(600.0);

    $movement = StockMovement::query()
        ->where('type', 'sale')
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->reference_type)->toBe('invoice')
        ->and($movement->reference_no)->toBe('C-000001')
        ->and((float) $movement->quantity)->toBe(600.0);

    $allocations = InvoiceItemAllocation::query()->get();

    expect($allocations)->toHaveCount(2)
        ->and((float) $allocations[0]->quantity)->toBe(400.0)
        ->and((float) $allocations[1]->quantity)->toBe(200.0);

    $ledger = CustomerLedgerEntry::query()->where('type', 'sale')->first();

    expect($ledger)->not->toBeNull()
        ->and((float) $ledger->debit)->toBe(75300.0)
        ->and((float) $ledger->balance_after)->toBe(75300.0);
});

it('rejects confirming a company invoice when warehouse stock is insufficient and rolls back', function () {
    $data = salesDataset();
    stockCompany($data, '100', '100');
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', companyInvoicePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['baseUnit']->id,
            'quantity' => '250',
            'unit_price' => '125.50',
        ]],
    ]))->assertCreated()->json('data.id');

    $response = $this->postJson("/api/v1/invoices/{$id}/confirm")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INSUFFICIENT_STOCK');

    expect($response->json('message'))->toContain($data['product']->name);

    expect(StockMovement::where('type', 'sale')->count())->toBe(0)
        ->and(InvoiceItemAllocation::count())->toBe(0)
        ->and(Invoice::findOrFail($id)->isDraft())->toBeTrue();
});

it('creates a distributor draft invoice priced from the earliest remaining custody batch', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/distributor/my/invoices', distributorInvoicePayload($data))
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'D-000001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.ownership', 'distributor')
        ->assertJsonPath('data.total_amount', '31375.00')
        ->assertJsonPath('data.items.0.unit_price', '125.50');
});

it('creates a distributor invoice when an admin sells to a distributor-owned customer', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/invoices', distributorInvoicePayload($data))
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'D-000001')
        ->assertJsonPath('data.ownership', 'distributor')
        ->assertJsonPath('data.sales_distributor.id', $data['distributor']->id)
        ->assertJsonPath('data.sales_distributor.name', $data['distributor']->user->name)
        ->assertJsonPath('data.total_amount', '31375.00')
        ->assertJsonPath('data.items.0.unit_price', '125.50');
});

it('rejects a distributor-owned draft when custody stock is unavailable', function () {
    $data = salesDataset();
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/v1/invoices', distributorInvoicePayload($data))
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INSUFFICIENT_DISTRIBUTOR_STOCK');

    expect($response->json('message'))->toContain($data['product']->name);
    expect(Invoice::count())->toBe(0);
});

it('confirms a distributor invoice from custody FIFO batches', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    $user = $data['distributor']->user;
    $user->refresh();

    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', distributorInvoicePayload($data))->json('data.id');

    $this->postJson("/api/v1/invoices/{$id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.total_amount', '31375.00');

    $batch = CustodyBatch::first();

    expect((float) $batch->remaining)->toBe(750.0);

    $inventory = DistributorInventory::query()
        ->where('distributor_id', $data['distributor']->id)
        ->where('product_id', $data['product']->id)
        ->first();

    expect((float) $inventory->quantity)->toBe(750.0);

    $invoice = Invoice::findOrFail($id);
    $invoice->items->load('allocations');

    expect(InvoiceItem::count())->toBe(1)
        ->and($invoice->items->first()->allocations->count())->toBe(1)
        ->and((float) $invoice->items->first()->unit_price)->toBe(125.5);

    $movement = CustodyMovement::query()->where('movement_type', 'sale')->first();

    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(250.0)
        ->and($movement->reference_type)->toBe('invoice');
});

it('rejects confirming a distributor invoice when custody stock is insufficient', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    $user = $data['distributor']->user;
    $user->refresh();

    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', distributorInvoicePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['baseUnit']->id,
            'quantity' => '2000',
        ]],
    ]))->json('data.id');

    $response = $this->postJson("/api/v1/invoices/{$id}/confirm")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'INSUFFICIENT_DISTRIBUTOR_STOCK');

    expect($response->json('message'))->toContain($data['product']->name);

    expect(CustodyMovement::where('movement_type', 'sale')->count())->toBe(0)
        ->and(InvoiceItem::count())->toBe(1)
        ->and(Invoice::findOrFail($id)->isDraft())->toBeTrue();
});

it('updates a draft invoice and replaces its items', function () {
    $data = salesDataset();
    stockCompany($data);
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', companyInvoicePayload($data))
        ->assertCreated()
        ->json('data.id');

    $this->putJson("/api/v1/invoices/{$id}", companyInvoicePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['baseUnit']->id,
            'quantity' => '100',
            'unit_price' => '200',
            'discount_amount' => '5',
        ]],
    ]))
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.total_amount', '19995.00')
        ->assertJsonCount(1, 'data.items');
});

it('refuses updating a confirmed invoice', function () {
    $data = salesDataset();
    stockCompany($data);
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', companyInvoicePayload($data))
        ->assertCreated()
        ->json('data.id');

    $this->postJson("/api/v1/invoices/{$id}/confirm")->assertOk();

    $this->putJson("/api/v1/invoices/{$id}", companyInvoicePayload($data, [
        'items' => [[
            'product_id' => $data['product']->id,
            'unit_id' => $data['baseUnit']->id,
            'quantity' => '100',
            'unit_price' => '200',
        ]],
    ]))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'CANNOT_EDIT_CONFIRMED_INVOICE');
});

it('refuses cancelling a confirmed company invoice', function () {
    $data = salesDataset();
    stockCompany($data);
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', companyInvoicePayload($data))
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/invoices/{$id}/confirm")->assertOk();

    $this->postJson("/api/v1/invoices/{$id}/cancel", [
        'cancellation_reason' => 'خطأ في الفاتورة',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_INVOICE_STATUS_TRANSITION');

    expect(Invoice::findOrFail($id)->isConfirmed())->toBeTrue()
        ->and((float) Inventory::query()
            ->where('product_id', $data['product']->id)
            ->where('warehouse_id', $data['warehouse']->id)
            ->first()->quantity)->toBe(750.0)
        ->and(StockMovement::where('type', 'sale_return')->count())->toBe(0)
        ->and(CustomerLedgerEntry::where('type', 'return')->count())->toBe(0);
});

it('refuses cancelling a confirmed distributor invoice', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    $user = $data['distributor']->user;
    $user->refresh();

    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', distributorInvoicePayload($data))->json('data.id');

    $this->postJson("/api/v1/invoices/{$id}/confirm")->assertOk();

    $this->postJson("/api/v1/invoices/{$id}/cancel", [
        'cancellation_reason' => 'العميل رفض البضاعة',
    ])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_INVOICE_STATUS_TRANSITION');

    $batch = CustodyBatch::first();

    expect((float) $batch->remaining)->toBe(750.0)
        ->and(CustodyMovement::where('movement_type', 'customer_return')->count())->toBe(0);
});

it('cancels a draft invoice without side effects', function () {
    $data = salesDataset();
    stockCompany($data);
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/invoices', companyInvoicePayload($data))
        ->assertCreated()->json('data.id');

    $this->postJson("/api/v1/invoices/{$id}/cancel", ['cancellation_reason' => 'إلغاء مسودة'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'إلغاء مسودة');

    expect(StockMovement::where('type', 'sale_return')->count())->toBe(0)
        ->and(CustomerLedgerEntry::where('type', 'return')->count())->toBe(0)
        ->and((float) Inventory::query()
            ->where('product_id', $data['product']->id)
            ->where('warehouse_id', $data['warehouse']->id)
            ->first()->quantity)->toBe(1000.0);

    $this->postJson("/api/v1/invoices/{$id}/cancel", ['cancellation_reason' => 'إلغاء ثانٍ'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_INVOICE_STATUS_TRANSITION');
});

it('confirms an invoice and records partial collections in one request', function () {
    $data = salesDataset();
    stockCompany($data);
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/invoices', companyInvoicePayload($data, [
        'confirm' => true,
        'collections' => [
            ['amount' => '10000', 'payment_method' => 'cash'],
            ['amount' => '5000', 'payment_method' => 'transfer'],
        ],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.paid_status', 'partial');

    $payments = CustomerLedgerEntry::query()->where('type', 'payment')->get();

    expect($payments)->toHaveCount(2)
        ->and((float) $payments->sum('credit'))->toBe(15000.0);

    $invoice = Invoice::where('invoice_number', 'C-000001')->firstOrFail();

    $this->getJson('/api/v1/invoices/'.$invoice->id)
        ->assertOk()
        ->assertJsonPath('data.paid_status', 'partial');

    $this->getJson('/api/v1/invoices?status=confirmed&ownership=company')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.invoice_number', 'C-000001');
});

it('lets a distributor see only their own invoices', function () {
    $data = salesDataset();
    $other = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    disbursePricedCustody($other, createSalesAdmin());
    $user = $data['distributor']->user;
    $user->refresh();
    $otherUser = $other['distributor']->user;
    $otherUser->refresh();

    Sanctum::actingAs($user, ['*']);
    $this->postJson('/api/v1/distributor/my/invoices', distributorInvoicePayload($data))
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'D-000001');

    Sanctum::actingAs($otherUser, ['*']);
    $this->postJson('/api/v1/distributor/my/invoices', distributorInvoicePayload($other))
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'D-000002');

    Sanctum::actingAs($user, ['*']);
    $rows = $this->getJson('/api/v1/distributor/my/invoices')->assertOk()->json('data');

    expect(count($rows))->toBe(1)
        ->and($rows[0]['invoice_number'])->toBe('D-000001');
});
