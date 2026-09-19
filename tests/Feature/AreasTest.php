<?php

use App\Modules\Areas\Models\Area;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Users\Models\User;
use Database\Seeders\AreasSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function areasAdmin(string $role = 'admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Areas Admin',
        'username' => 'areas_admin_'.uniqid(),
        'email' => 'areas_'.$role.'_'.uniqid().'@example.com',
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

it('seeds the Egyptian governorates and districts', function () {
    $governorates = [
        'القاهرة', 'الجيزة', 'الإسكندرية', 'الدقهلية', 'الشرقية', 'البحيرة',
        'المنوفية', 'القليوبية', 'الغربية', 'الفيوم', 'المنيا', 'أسيوط',
        'سوهاج', 'بني سويف', 'قنا', 'أسوان', 'الأقصر', 'الإسماعيلية',
        'بورسعيد', 'دمياط', 'كفر الشيخ', 'مطروح', 'البحر الأحمر',
        'شمال سيناء', 'جنوب سيناء', 'السويس', 'الوادي الجديد',
    ];

    (new AreasSeeder)->run();

    expect(Area::count())->toBeGreaterThan(27);
    expect(Area::where('name', 'القاهرة')->exists())->toBeTrue();
    expect(Area::where('name', 'الزمالك')->exists())->toBeTrue();
    expect(Area::where('name', 'شرم الشيخ')->exists())->toBeTrue();
    foreach ($governorates as $governorate) {
        expect(Area::where('name', $governorate)->exists())->toBeTrue();
    }
});

it('lists areas for a user with areas.view', function () {
    Area::create(['name' => 'القاهرة']);
    Area::create(['name' => 'الجيزة']);

    Sanctum::actingAs(areasAdmin('admin', ['areas.view']), ['*']);

    $this->getJson('/api/v1/areas')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name']], 'meta']);
});

it('creates an area', function () {
    Sanctum::actingAs(areasAdmin('admin', ['areas.create']), ['*']);

    $this->postJson('/api/v1/areas', ['name' => 'المنوفية'])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'المنوفية');

    $this->assertDatabaseHas('areas', ['name' => 'المنوفية']);
});

it('rejects a duplicate area name', function () {
    Area::create(['name' => 'الفيوم']);

    Sanctum::actingAs(areasAdmin('admin', ['areas.create']), ['*']);

    $this->postJson('/api/v1/areas', ['name' => 'الفيوم'])
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['name']]);
});

it('updates an area', function () {
    $area = Area::create(['name' => 'الفيوم']);

    Sanctum::actingAs(areasAdmin('admin', ['areas.update']), ['*']);

    $this->putJson("/api/v1/areas/{$area->id}", ['name' => 'الفيوم الجديدة'])
        ->assertOk()
        ->assertJsonPath('data.name', 'الفيوم الجديدة');

    expect($area->fresh()->name)->toBe('الفيوم الجديدة');
});

it('deletes an area', function () {
    $area = Area::create(['name' => 'بورسعيد']);

    Sanctum::actingAs(areasAdmin('admin', ['areas.delete']), ['*']);

    $this->deleteJson("/api/v1/areas/{$area->id}")
        ->assertNoContent();

    expect(Area::find($area->id))->toBeNull();
});

it('forbids deleting an area that is assigned to distributors', function () {
    $area = Area::create(['name' => 'القاهرة']);

    $user = User::create([
        'name' => 'موزع مرتبط',
        'email' => 'linked_dist_'.uniqid().'@example.com',
        'password' => 'P@ssw0rd!',
        'role' => 'distributor',
        'is_active' => true,
        'must_change_password' => false,
    ]);
    $user->assignRole('distributor');

    $distributor = Distributor::create(['user_id' => $user->id, 'status' => 'active']);
    $distributor->areas()->attach($area->id);

    Sanctum::actingAs(areasAdmin('admin', ['areas.delete']), ['*']);

    $this->deleteJson("/api/v1/areas/{$area->id}")
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'AREA_IN_USE');
});

it('forbids managing areas without permission', function () {
    Area::create(['name' => 'سوهاج']);

    Sanctum::actingAs(areasAdmin('customer_service'), ['*']);

    $this->getJson('/api/v1/areas')->assertForbidden();
    $this->postJson('/api/v1/areas', ['name' => 'مطروح'])->assertForbidden();
});

it('publishes a localized validation error in Arabic', function () {
    Sanctum::actingAs(areasAdmin('admin', ['areas.create']), ['*']);

    $this->withHeader('X-Locale', 'ar')
        ->postJson('/api/v1/areas', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'حقل الاسم مطلوب.');
});
