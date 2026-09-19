<?php

use App\Modules\Units\Models\Unit;
use App\Modules\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createUnitUser(string $role = 'admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Unit User',
        'username' => 'unit_user_'.$role.uniqid(),
        'email' => 'unit_'.$role.'_'.uniqid().'@example.com',
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

it('lets super admin create a unit', function () {
    $user = createUnitUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/units', [
        'name' => 'كيلوجرام',
        'symbol' => 'كجم',
        'decimal_places' => 3,
        'is_weight' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'كيلوجرام')
        ->assertJsonStructure(['data' => ['id', 'name', 'symbol', 'decimal_places', 'is_weight', 'is_active']]);
});

it('lets admin create a quantity unit with defaults', function () {
    $user = createUnitUser('admin', ['units.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/units', [
        'name' => 'قطعة',
    ])
        ->assertCreated()
        ->assertJsonPath('data.decimal_places', 0)
        ->assertJsonPath('data.is_weight', false)
        ->assertJsonPath('data.is_active', true);
});

it('rejects a unit without a name', function () {
    $user = createUnitUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/units', [])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['name']]);
});

it('rejects duplicate unit names', function () {
    Unit::create(['name' => 'كيلوجرام', 'is_active' => true]);

    $user = createUnitUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/units', ['name' => 'كيلوجرام'])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['name']]);
});

it('rejects out of range decimal_places', function () {
    $user = createUnitUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/units', [
        'name' => 'وحدة غلط',
        'decimal_places' => 5,
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['decimal_places']]);
});

it('forbids a user without units.create permission', function () {
    $user = createUnitUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/units', ['name' => 'كيس'])->assertForbidden();
});

it('lists units for a user with units.view', function () {
    Unit::create(['name' => 'كيلوجرام', 'is_active' => true]);
    Unit::create(['name' => 'قطعة', 'is_active' => true]);

    $user = createUnitUser('admin', ['units.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/units')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'symbol', 'decimal_places', 'is_weight', 'is_active']], 'meta']);
});

it('filters units by is_active status', function () {
    Unit::create(['name' => 'كيلوجرام', 'is_active' => true]);
    Unit::create(['name' => 'قديم', 'is_active' => false]);

    $user = createUnitUser('admin', ['units.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/units?is_active=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'قديم');
});

it('filters units by is_weight type', function () {
    Unit::create(['name' => 'كيلوجرام', 'is_weight' => true, 'is_active' => true]);
    Unit::create(['name' => 'قطعة', 'is_weight' => false, 'is_active' => true]);

    $user = createUnitUser('admin', ['units.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/units?is_weight=true')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'كيلوجرام');
});

it('shows a single unit', function () {
    $unit = Unit::create(['name' => 'لتر', 'is_active' => true]);

    $user = createUnitUser('admin', ['units.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/v1/units/{$unit->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'لتر')
        ->assertJsonPath('data.id', $unit->id);
});

it('updates a unit', function () {
    $unit = Unit::create(['name' => 'قطعة', 'is_active' => true]);

    $user = createUnitUser('admin', ['units.update']);
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/units/{$unit->id}", [
        'name' => 'دستة',
        'is_active' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'دستة')
        ->assertJsonPath('data.is_active', false);
});

it('deletes a unit', function () {
    $unit = Unit::create(['name' => 'وحدة مؤقتة', 'is_active' => true]);

    $user = createUnitUser('admin', ['units.delete']);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/units/{$unit->id}")
        ->assertNoContent();

    expect(Unit::find($unit->id))->toBeNull();
});

it('forbids deleting a unit without permission', function () {
    $unit = Unit::create(['name' => 'قطعة', 'is_active' => true]);

    $user = createUnitUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/units/{$unit->id}")->assertForbidden();
});

it('forbids a distributor from listing all units', function () {
    $user = createUnitUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/units')->assertForbidden();
});
