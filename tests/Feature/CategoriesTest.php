<?php

use App\Modules\Categories\Models\Category;
use App\Modules\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createCategoryUser(string $role = 'admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Category User',
        'username' => 'category_user_'.$role.uniqid(),
        'email' => 'category_'.$role.'_'.uniqid().'@example.com',
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

it('lets super admin create a category', function () {
    $user = createCategoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/categories', [
        'name' => 'مشروبات',
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'مشروبات')
        ->assertJsonStructure(['data' => ['id', 'name', 'code', 'status']]);
});

it('lets admin create a category with parent', function () {
    $parent = Category::create([
        'name' => 'مواد غذائية',
        'code' => Category::generateCode(),
        'status' => 'active',
    ]);

    $user = createCategoryUser('admin', ['categories.create']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/categories', [
        'name' => 'أرز',
        'parent_id' => $parent->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id);
});

it('auto-generates a unique code when none provided', function () {
    $user = createCategoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/categories', ['name' => 'مشروبات'])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['code']]);
});

it('rejects a category without a name', function () {
    $user = createCategoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/categories', [])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['name']]);
});

it('rejects duplicate category names', function () {
    Category::create([
        'name' => 'مشروبات',
        'code' => Category::generateCode(),
        'status' => 'active',
    ]);

    $user = createCategoryUser('super_admin');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/categories', ['name' => 'مشروبات'])
        ->assertUnprocessable();
});

it('forbids a user without categories.create permission', function () {
    $user = createCategoryUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/categories', ['name' => 'مشروبات'])->assertForbidden();
});

it('lists categories for a user with categories.view', function () {
    Category::create(['name' => 'مشروبات', 'code' => Category::generateCode(), 'status' => 'active']);
    Category::create(['name' => 'مواد غذائية', 'code' => Category::generateCode(), 'status' => 'active']);

    $user = createCategoryUser('admin', ['categories.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'code', 'status']], 'meta']);
});

it('filters categories by status', function () {
    Category::create(['name' => 'نشطة', 'code' => Category::generateCode(), 'status' => 'active']);
    Category::create(['name' => 'غير نشطة', 'code' => Category::generateCode(), 'status' => 'inactive']);

    $user = createCategoryUser('admin', ['categories.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/categories?status=inactive')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'غير نشطة');
});

it('shows a single category with children', function () {
    $parent = Category::create(['name' => 'مشروبات', 'code' => Category::generateCode(), 'status' => 'active']);
    Category::create(['name' => 'عصائر', 'code' => Category::generateCode(), 'status' => 'active', 'parent_id' => $parent->id]);

    $user = createCategoryUser('admin', ['categories.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/v1/categories/{$parent->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'مشروبات')
        ->assertJsonCount(1, 'data.children');
});

it('updates a category', function () {
    $category = Category::create(['name' => 'مشروبات', 'code' => Category::generateCode(), 'status' => 'active']);

    $user = createCategoryUser('admin', ['categories.update']);
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/categories/{$category->id}", [
        'name' => 'مشروبات غازية',
        'status' => 'inactive',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'مشروبات غازية')
        ->assertJsonPath('data.status', 'inactive');
});

it('rejects a self parent when updating', function () {
    $category = Category::create(['name' => 'مشروبات', 'code' => Category::generateCode(), 'status' => 'active']);

    $user = createCategoryUser('admin', ['categories.update']);
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/categories/{$category->id}", [
        'parent_id' => $category->id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'CATEGORY_CANNOT_DELETE');
});

it('rejects moving a category under its own descendant', function () {
    $parent = Category::create(['name' => 'مشروبات', 'code' => Category::generateCode(), 'status' => 'active']);
    $child = Category::create(['name' => 'عصائر', 'code' => Category::generateCode(), 'status' => 'active', 'parent_id' => $parent->id]);

    $user = createCategoryUser('admin', ['categories.update']);
    Sanctum::actingAs($user, ['*']);

    $this->putJson("/api/v1/categories/{$parent->id}", [
        'parent_id' => $child->id,
    ])
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'CATEGORY_CANNOT_DELETE');
});

it('cannot delete a category that has children', function () {
    $parent = Category::create(['name' => 'مشروبات', 'code' => Category::generateCode(), 'status' => 'active']);
    Category::create(['name' => 'عصائر', 'code' => Category::generateCode(), 'status' => 'active', 'parent_id' => $parent->id]);

    $user = createCategoryUser('admin', ['categories.delete']);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/categories/{$parent->id}")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'CATEGORY_HAS_CHILDREN');

    expect(Category::find($parent->id))->not->toBeNull();
});

it('deletes a leaf category', function () {
    $category = Category::create(['name' => 'عصائر', 'code' => Category::generateCode(), 'status' => 'active']);

    $user = createCategoryUser('admin', ['categories.delete']);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/categories/{$category->id}")
        ->assertNoContent();

    expect(Category::find($category->id))->toBeNull();
});

it('forbids deleting a category without permission', function () {
    $category = Category::create(['name' => 'مشروبات', 'code' => Category::generateCode(), 'status' => 'active']);

    $user = createCategoryUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson("/api/v1/categories/{$category->id}")->assertForbidden();
});

it('forbids a distributor from listing all categories', function () {
    $user = createCategoryUser('distributor');
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/categories')->assertForbidden();
});
