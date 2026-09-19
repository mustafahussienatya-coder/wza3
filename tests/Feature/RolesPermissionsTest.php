<?php

use App\Modules\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createUserWithRole(string $role): User
{
    $user = User::create([
        'name' => 'User',
        'username' => 'user_'.$role.uniqid(),
        'email' => $role.'_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => $role,
        'is_active' => true,
        'must_change_password' => false,
    ]);

    $user->assignRole($role);

    return $user;
}

it('lets a user with roles.view permission list roles', function () {
    $user = createUserWithRole('admin');
    $user->givePermissionTo('roles.view');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => [['id', 'name', 'system', 'permissions']]]);
});

it('rejects a user without roles.view permission', function () {
    $user = createUserWithRole('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/roles')->assertForbidden();
});

it('lets super admin list roles via bypass', function () {
    $user = createUserWithRole('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('lists all permissions grouped by module', function () {
    $user = createUserWithRole('admin');
    $user->givePermissionTo('permissions.view');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/permissions')
        ->assertOk()
        ->assertJsonStructure(['data' => [['module', 'permissions']]]);
});

it('updates role permissions with roles.manage permission', function () {
    $role = Role::findByName('distributor');

    $user = createUserWithRole('admin');
    $user->givePermissionTo(['roles.view', 'roles.manage']);
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/roles/{$role->id}/permissions", [
        'permissions' => ['customers.view', 'orders.view'],
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['customers.view', 'orders.view']);
});

it('rejects invalid permission names when updating a role', function () {
    $role = Role::findByName('distributor');

    $user = createUserWithRole('admin');
    $user->givePermissionTo(['roles.view', 'roles.manage']);
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/roles/{$role->id}/permissions", [
        'permissions' => ['does.not.exist'],
    ])->assertUnprocessable();
});

it('forbids a distributor from managing role permissions', function () {
    $role = Role::findByName('distributor');

    $user = createUserWithRole('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/roles/{$role->id}/permissions", [
        'permissions' => ['customers.view'],
    ])->assertForbidden();
});

it('returns the users permissions from the database in the resource', function () {
    $user = createUserWithRole('distributor');
    $user->givePermissionTo('customers.view');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.role', 'distributor')
        ->assertJsonFragment(['customers.view'])
        ->assertJsonStructure(['data' => ['permissions', 'roles']]);
});
