<?php

use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createWarehouseUser(string $role = 'admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Warehouse User',
        'username' => 'warehouse_user_'.$role.uniqid(),
        'email' => 'warehouse_'.$role.'_'.uniqid().'@example.com',
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

function createManagerUser(): User
{
    $user = User::create([
        'name' => 'Warehouse Manager',
        'username' => 'warehouse_manager_'.uniqid(),
        'email' => 'warehouse_manager_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => 'admin',
        'is_active' => true,
        'must_change_password' => false,
    ]);

    $user->assignRole('admin');

    return $user;
}

it('lets super admin create a warehouse', function () {
    $manager = createManagerUser();
    $user = createWarehouseUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/warehouses', [
        'name' => 'المخزن الرئيسي',
        'location' => 'القاهرة',
        'manager_id' => $manager->id,
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'المخزن الرئيسي')
        ->assertJsonPath('data.location', 'القاهرة')
        ->assertJsonPath('data.manager.name', 'Warehouse Manager')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['data' => ['id', 'name', 'code', 'status', 'manager']]);
});

it('generates a unique warehouse code automatically', function () {
    $user = createWarehouseUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/warehouses', [
        'name' => 'المخزن الرئيسي',
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', fn ($code) => str_starts_with($code, 'WH-'));
});

it('lets admin create a warehouse with defaults', function () {
    $user = createWarehouseUser('admin', ['warehouses.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/warehouses', [
        'name' => 'مخزن الفرع',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.manager_id', null);
});

it('rejects a warehouse without a name', function () {
    $user = createWarehouseUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/warehouses', [])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['name']]);
});

it('rejects a warehouse with invalid manager_id', function () {
    $user = createWarehouseUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/warehouses', [
        'name' => 'مخزن غلط',
        'manager_id' => 999999,
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['manager_id']]);
});

it('rejects invalid warehouse status', function () {
    $user = createWarehouseUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/warehouses', [
        'name' => 'مخزن غلط',
        'status' => 'archived',
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['status']]);
});

it('forbids a user without warehouses.create permission', function () {
    $user = createWarehouseUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/warehouses', ['name' => 'مخزن'])->assertForbidden();
});

it('lists warehouses for a user with warehouses.view', function () {
    Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-001', 'status' => 'active']);
    Warehouse::create(['name' => 'مخزن الفرع', 'code' => 'WH-002', 'status' => 'active']);

    $user = createWarehouseUser('admin', ['warehouses.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/warehouses')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'code', 'status']], 'meta']);
});

it('searches warehouses by name or code', function () {
    Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-001', 'status' => 'active']);
    Warehouse::create(['name' => 'مخزن الفرع', 'code' => 'WH-002', 'status' => 'active']);

    $user = createWarehouseUser('admin', ['warehouses.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/warehouses?search=WH-002')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'WH-002');
});

it('filters warehouses by status', function () {
    Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-001', 'status' => 'active']);
    Warehouse::create(['name' => 'مخزن معطل', 'code' => 'WH-002', 'status' => 'inactive']);

    $user = createWarehouseUser('admin', ['warehouses.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/warehouses?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'مخزن معطل');
});

it('shows a single warehouse', function () {
    $warehouse = Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-001', 'status' => 'active']);

    $user = createWarehouseUser('admin', ['warehouses.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/v1/warehouses/{$warehouse->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'المخزن الرئيسي')
        ->assertJsonPath('data.id', $warehouse->id);
});

it('updates a warehouse', function () {
    $warehouse = Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-001', 'status' => 'active']);

    $user = createWarehouseUser('admin', ['warehouses.update']);
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/warehouses/{$warehouse->id}", [
        'name' => 'المخزن الرئيسي المحدث',
        'status' => 'inactive',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'المخزن الرئيسي المحدث')
        ->assertJsonPath('data.status', 'inactive');
});

it('deletes a warehouse', function () {
    $warehouse = Warehouse::create(['name' => 'مخزن مؤقت', 'code' => 'WH-001', 'status' => 'active']);

    $user = createWarehouseUser('admin', ['warehouses.delete']);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/warehouses/{$warehouse->id}")
        ->assertNoContent();

    expect(Warehouse::find($warehouse->id))->toBeNull();
});

it('forbids deleting a warehouse without permission', function () {
    $warehouse = Warehouse::create(['name' => 'المخزن الرئيسي', 'code' => 'WH-001', 'status' => 'active']);

    $user = createWarehouseUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/warehouses/{$warehouse->id}")->assertForbidden();
});

it('forbids a distributor from listing all warehouses', function () {
    $user = createWarehouseUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/warehouses')->assertForbidden();
});
