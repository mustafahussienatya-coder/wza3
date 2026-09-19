<?php

use App\Modules\Categories\Models\Category;
use App\Modules\Distributors\Actions\ReturnDistributorIssueAction;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Inventory\Actions\AddStockInAction;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Notifications\Notifications\AppNotification;
use App\Modules\Products\Models\Product;
use App\Modules\Units\Models\Unit;
use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createNotifUser(string $role = 'super_admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Notif User',
        'username' => 'notif_'.$role.uniqid(),
        'email' => 'notif_'.$role.'_'.uniqid().'@example.com',
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

function makeNotifDistributor(): Distributor
{
    $user = User::create([
        'name' => 'الموزع فلان',
        'username' => 'dist_'.uniqid(),
        'email' => 'dist_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => 'distributor',
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $user->assignRole('distributor');

    return Distributor::create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);
}

function notifDataset(string $productName = 'سكر'): array
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
        'distributor' => makeNotifDistributor(),
        'baseUnit' => $kg,
        'tonUnit' => $ton,
    ];
}

function stockNotifWarehouse(array $data, string $quantity = '1000'): void
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

function notifIssuePayload(array $data): array
{
    return [
        'distributor_id' => $data['distributor']->id,
        'warehouse_id' => $data['warehouse']->id,
        'notes' => 'تسليم عهدة أولية',
        'items' => [
            ['product_id' => $data['product']->id, 'unit_id' => $data['tonUnit']->id, 'quantity' => 1, 'unit_price' => '0'],
        ],
    ];
}

function makeNotifIssue(array $data, User $user, string $status = 'pending_approval'): DistributorIssue
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

function notificationSnapshots(): array
{
    return DB::table('notifications')
        ->orderBy('id')
        ->get()
        ->map(fn (object $row) => [
            'notifiable_id' => (int) $row->notifiable_id,
            'code' => json_decode($row->data, true)['code'],
            'link' => json_decode($row->data, true)['link'],
        ])
        ->all();
}

it('notifies approval users when an issue is submitted, excluding the submitter', function () {
    $data = notifDataset();
    $actor = createNotifUser('super_admin');
    $approver = createNotifUser('admin');
    $customerService = createNotifUser('customer_service');
    Sanctum::actingAs($actor, ['*']);

    $issue = DistributorIssue::find(
        $this->postJson('/api/v1/distributor-issues', notifIssuePayload($data))
            ->assertCreated()
            ->json('data.id')
    );

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/submit")->assertOk();

    expect(notificationSnapshots())->toBe([
        ['notifiable_id' => $approver->id, 'code' => 'custody.approval_requested', 'link' => '/custody/disburse'],
    ]);

    expect(DB::table('notifications')->where('notifiable_id', $customerService->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('notifiable_id', $actor->id)->count())->toBe(0);
});

it('notifies the super admin even when the submitting admin holds the approve permission', function () {
    $data = notifDataset();
    $actor = createNotifUser('admin');
    $superAdmin = createNotifUser('super_admin');
    Sanctum::actingAs($actor, ['*']);

    $issue = DistributorIssue::find(
        $this->postJson('/api/v1/distributor-issues', notifIssuePayload($data))
            ->assertCreated()
            ->json('data.id')
    );

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/submit")->assertOk();

    expect(notificationSnapshots())->toBe([
        ['notifiable_id' => $superAdmin->id, 'code' => 'custody.approval_requested', 'link' => '/custody/disburse'],
    ]);

    expect(DB::table('notifications')->where('notifiable_id', $actor->id)->count())->toBe(0);
});

it('does not notify anyone when a draft issue is submitted by its creator alone', function () {
    $data = notifDataset();
    $actor = createNotifUser('super_admin');
    Sanctum::actingAs($actor, ['*']);

    $issue = DistributorIssue::find(
        $this->postJson('/api/v1/distributor-issues', notifIssuePayload($data))
            ->assertCreated()
            ->json('data.id')
    );

    $this->postJson("/api/v1/distributor-issues/{$issue->id}/submit")->assertOk();

    expect(DB::table('notifications')->count())->toBe(0);
});

it('notifies the creator when an issue is approved', function () {
    $data = notifDataset();
    stockNotifWarehouse($data, '2000');
    $creator = createNotifUser('super_admin');
    $approver = createNotifUser('admin');
    $issue = makeNotifIssue($data, $creator, 'pending_approval');

    Sanctum::actingAs($approver, ['*']);
    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect(notificationSnapshots())->toBe([
        ['notifiable_id' => $creator->id, 'code' => 'custody.approved', 'link' => '/custody/follow'],
    ]);
});

it('notifies the creator exactly once with custody.disbursed on approve-disburse', function () {
    $data = notifDataset();
    stockNotifWarehouse($data, '1000');
    $creator = createNotifUser('super_admin');
    $approver = createNotifUser('admin');
    $issue = makeNotifIssue($data, $creator, 'pending_approval');

    Sanctum::actingAs($approver, ['*']);
    $this->postJson("/api/v1/distributor-issues/{$issue->id}/approve-disburse")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect(notificationSnapshots())->toBe([
        ['notifiable_id' => $creator->id, 'code' => 'custody.disbursed', 'link' => '/custody/follow'],
    ]);
});

it('notifies the creator when a completed issue is returned to the warehouse', function () {
    $data = notifDataset();
    stockNotifWarehouse($data, '1000');
    $creator = createNotifUser('super_admin');
    $admin = createNotifUser('admin');
    $issue = makeNotifIssue($data, $creator, 'approved');

    Sanctum::actingAs($creator, ['*']);
    $this->postJson("/api/v1/distributor-issues/{$issue->id}/complete")->assertOk();

    app(ReturnDistributorIssueAction::class)->execute($issue->refresh(), (int) $admin->id);

    expect(notificationSnapshots())->toBe([
        ['notifiable_id' => $creator->id, 'code' => 'custody.returned', 'link' => '/custody/follow'],
    ]);
});

it('notifies approval users when a pending issue is cancelled, excluding the canceller', function () {
    $data = notifDataset();
    $creator = createNotifUser('super_admin');
    $canceller = createNotifUser('admin');
    $issue = makeNotifIssue($data, $creator, 'pending_approval');

    Sanctum::actingAs($canceller, ['*']);
    $this->postJson("/api/v1/distributor-issues/{$issue->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect(notificationSnapshots())->toBe([
        ['notifiable_id' => $creator->id, 'code' => 'custody.cancelled', 'link' => '/custody/disburse'],
    ]);
});

it('does not notify anyone when a draft issue is cancelled', function () {
    $data = notifDataset();
    $creator = createNotifUser('super_admin');
    $issue = makeNotifIssue($data, $creator, 'draft');

    Sanctum::actingAs($creator, ['*']);
    $this->postJson("/api/v1/distributor-issues/{$issue->id}/cancel")->assertOk();

    expect(DB::table('notifications')->count())->toBe(0);
});

it('notifies users with the inventory.correct permission when a stock movement is corrected, excluding the actor', function () {
    $data = notifDataset();
    $actor = createNotifUser('admin');
    $superAdmin = createNotifUser('super_admin');
    $otherAdmin = createNotifUser('admin');
    $distributor = createNotifUser('distributor');
    Sanctum::actingAs($actor, ['*']);

    $this->postJson('/api/v1/inventory/stock-in', [
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
        ->assertJsonPath('data.reason', 'entry_error');

    $snapshots = notificationSnapshots();

    expect($snapshots)->toHaveCount(2)
        ->and(collect($snapshots)->pluck('notifiable_id')->sort()->values()->all())->toBe([
            $superAdmin->id,
            $otherAdmin->id,
        ])
        ->and(collect($snapshots)->pluck('code')->unique()->values()->all())->toBe(['inventory.corrected'])
        ->and(collect($snapshots)->pluck('link')->unique()->values()->all())->toBe(['/warehouses/movements']);

    expect(DB::table('notifications')->where('notifiable_id', $distributor->id)->count())->toBe(0)
        ->and(DB::table('notifications')->where('notifiable_id', $actor->id)->count())->toBe(0);
});

it('returns only the authenticated users notifications', function () {
    $actor = createNotifUser('super_admin');
    $other = createNotifUser('super_admin');

    $actor->notify(new AppNotification('custody.approved', ['issue_number' => 'DI-00001'], '/custody/follow'));
    $other->notify(new AppNotification('inventory.corrected', ['product_name' => 'سكر'], '/warehouses/movements'));

    Sanctum::actingAs($actor, ['*']);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'custody.approved')
        ->assertJsonPath('data.0.link', '/custody/follow')
        ->assertJsonPath('meta.total', 1);
});

it('returns the unread count for the authenticated user', function () {
    $user = createNotifUser('super_admin');
    $user->notify(new AppNotification('custody.approved'));
    DB::table('notifications')->whereNull('read_at')->update(['read_at' => now()]);
    $user->notify(new AppNotification('custody.returned'));

    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread_count', 1);
});

it('marks a single notification as read and returns an ISO read_at', function () {
    $user = createNotifUser('super_admin');
    $user->notify(new AppNotification('custody.approved'));
    $id = DB::table('notifications')->first()->id;

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $id);

    expect($response->json('data.read_at'))->not->toBeNull();

    expect(DB::table('notifications')->where('id', $id)->value('read_at'))->not->toBeNull();
});

it('marks all notifications as read', function () {
    $user = createNotifUser('super_admin');
    $user->notify(new AppNotification('custody.approved'));
    $user->notify(new AppNotification('custody.returned'));

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/notifications/read-all')->assertOk();

    expect(DB::table('notifications')->whereNull('read_at')->count())->toBe(0);
});

it('returns 404 when trying to read another users notification', function () {
    $actor = createNotifUser('super_admin');
    $other = createNotifUser('super_admin');
    $other->notify(new AppNotification('custody.approved'));
    $id = DB::table('notifications')->first()->id;

    Sanctum::actingAs($actor, ['*']);

    $this->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
});

it('requires authentication for the notifications endpoints', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
});
