<?php

use App\Enums\UserRole;
use App\Modules\Areas\Models\Area;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function createUser(string $role = 'super_admin', array $permissions = []): User
{
    $user = User::create([
        'name' => 'Test User',
        'username' => 'user_'.$role.uniqid(),
        'email' => 'user_'.$role.'_'.uniqid().'@example.com',
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

function distributorUserPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'موزع جديد',
        'email' => 'newdist@example.com',
        'username' => 'newdist',
        'password' => 'P@ssw0rd!',
        'password_confirmation' => 'P@ssw0rd!',
        'phone' => '01000000000',
        'role' => UserRole::DISTRIBUTOR->value,
    ], $overrides);
}

it('creates a distributor user through the users endpoint and auto-creates the distributor record', function () {
    Sanctum::actingAs(createUser(), ['*']);

    $this->postJson('/api/v1/users', distributorUserPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.role', 'distributor')
        ->assertJsonPath('data.is_distributor', true)
        ->assertJsonPath('data.distributor.status', 'active');

    $created = User::where('email', 'newdist@example.com')->firstOrFail();

    expect($created->hasRole(UserRole::DISTRIBUTOR->value))->toBeTrue();
    $this->assertDatabaseHas('distributors', ['user_id' => $created->id, 'status' => 'active']);
});

it('creates a distributor with vehicle details and areas', function () {
    $area = Area::create(['name' => 'القاهرة']);
    Sanctum::actingAs(createUser(), ['*']);

    $this->postJson('/api/v1/users', distributorUserPayload([
        'distributor' => [
            'vehicle_number' => '1234',
            'vehicle_type' => 'tuk_tuk',
            'area_ids' => [$area->id],
        ],
    ]))->assertCreated();

    $distributor = User::where('email', 'newdist@example.com')->firstOrFail()->distributor;

    expect($distributor->vehicle_number)->toBe('1234');
    expect($distributor->vehicle_type->value)->toBe('tuk_tuk');
    expect($distributor->areas()->pluck('areas.id')->toArray())->toBe([$area->id]);
});

it('rejects a distributor block when the user role is not distributor', function () {
    Sanctum::actingAs(createUser(), ['*']);

    $this->postJson('/api/v1/users', distributorUserPayload([
        'role' => 'admin',
        'distributor' => ['vehicle_number' => '1234'],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('error_code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['distributor']]);
});

it('lists distributors with vehicle details and areas', function () {
    $area = Area::create(['name' => 'الجيزة']);
    $distributor = makeDistributor(['name' => 'أحمد']);
    $distributor->update(['vehicle_number' => '5555', 'vehicle_type' => 'car']);
    $distributor->areas()->attach($area->id);

    Sanctum::actingAs(createUser('admin', ['distributors.view']), ['*']);

    $this->getJson('/api/v1/distributors')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'أحمد')
        ->assertJsonPath('data.0.vehicle_number', '5555')
        ->assertJsonPath('data.0.vehicle_type', 'car')
        ->assertJsonPath('data.0.areas.0.id', $area->id)
        ->assertJsonPath('data.0.national_id_documents.front', null)
        ->assertJsonStructure(['data' => [['id', 'user_id', 'name', 'email', 'status']], 'meta']);
});

it('filters distributors by operations status', function () {
    makeDistributor(['name' => 'نشط', 'status' => 'active', 'phone' => '01011111111']);
    makeDistributor(['name' => 'موقوف', 'status' => 'suspended', 'phone' => '01022222222']);

    Sanctum::actingAs(createUser('admin', ['distributors.view']), ['*']);

    $this->getJson('/api/v1/distributors?status=suspended')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'موقوف');
});

it('filters distributors by login status', function () {
    makeDistributor(['name' => 'نشط', 'is_active' => true, 'phone' => '01011111111']);
    makeDistributor(['name' => 'معطل دخول', 'is_active' => false, 'phone' => '01022222222']);

    Sanctum::actingAs(createUser('admin', ['distributors.view']), ['*']);

    $this->getJson('/api/v1/distributors?is_active=false')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'معطل دخول');
});

it('searches distributors by name', function () {
    makeDistributor(['name' => 'أحمد محمد', 'phone' => '01011111111']);
    makeDistributor(['name' => 'محمود علي', 'phone' => '01022222222']);

    Sanctum::actingAs(createUser('admin', ['distributors.view']), ['*']);

    $this->getJson('/api/v1/distributors?search='.urlencode('أحمد'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'أحمد محمد');
});

it('shows a single distributor for admins', function () {
    $distributor = makeDistributor(['name' => 'حسام', 'phone' => '01033333333']);

    Sanctum::actingAs(createUser('admin', ['distributors.view']), ['*']);

    $this->getJson("/api/v1/distributors/{$distributor->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $distributor->id)
        ->assertJsonPath('data.name', 'حسام');
});

it('lets a distributor view only their own distributor record', function () {
    $own = makeDistributor(['name' => 'صاحب الحساب', 'email' => 'owner@example.com']);
    $other = makeDistributor(['name' => 'موزع آخر', 'email' => 'other@example.com']);

    Sanctum::actingAs($own->user, ['*']);

    $this->getJson("/api/v1/distributors/{$own->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'صاحب الحساب');

    $this->getJson("/api/v1/distributors/{$other->id}")->assertForbidden();
});

it('forbids a distributor from listing all distributors', function () {
    $own = makeDistributor();

    Sanctum::actingAs($own->user, ['*']);

    $this->getJson('/api/v1/distributors')->assertForbidden();
});

it('suspends and activates a distributor through the status endpoint', function () {
    $distributor = makeDistributor();

    Sanctum::actingAs(createUser('admin', ['distributors.update']), ['*']);

    $this->patchJson("/api/v1/distributors/{$distributor->id}/status", ['status' => 'suspended'])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect($distributor->fresh()->isSuspended())->toBeTrue();

    $this->patchJson("/api/v1/distributors/{$distributor->id}/status", ['status' => 'active'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($distributor->fresh()->isActive())->toBeTrue();
});

it('rejects an invalid status value', function () {
    $distributor = makeDistributor();

    Sanctum::actingAs(createUser('admin', ['distributors.update']), ['*']);

    $this->patchJson("/api/v1/distributors/{$distributor->id}/status", ['status' => 'banned'])
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['status']]);
});

it('forbids a status change without permission', function () {
    $distributor = makeDistributor();

    Sanctum::actingAs(createUser('customer_service', ['distributors.view']), ['*']);

    $this->patchJson("/api/v1/distributors/{$distributor->id}/status", ['status' => 'suspended'])
        ->assertForbidden();
});

it('updates a distributor vehicle details through the user endpoint', function () {
    $distributor = makeDistributor();

    Sanctum::actingAs(createUser('admin', ['users.update']), ['*']);

    $this->putJson("/api/v1/users/{$distributor->user_id}", [
        'distributor' => [
            'vehicle_number' => '9999',
            'vehicle_type' => 'pickup',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.distributor.vehicle_number', '9999')
        ->assertJsonPath('data.distributor.vehicle_type', 'pickup');

    expect($distributor->fresh()->vehicle_number)->toBe('9999');
});

it('deletes the distributor record when the role changes away from distributor', function () {
    $distributor = makeDistributor();

    Sanctum::actingAs(createUser('admin', ['users.update']), ['*']);

    $this->putJson("/api/v1/users/{$distributor->user_id}", ['role' => 'customer_service'])
        ->assertOk()
        ->assertJsonPath('data.is_distributor', false);

    expect(Distributor::find($distributor->id))->toBeNull();
});

it('stores national ID photos privately and serves them through the protected endpoint', function () {
    Storage::fake('private');

    $distributor = makeDistributor(['name' => 'محمود']);
    Sanctum::actingAs(createUser('admin', ['users.update']), ['*']);

    $this->putJson("/api/v1/users/{$distributor->user_id}", [
        'distributor' => [
            'national_id_photo_front' => UploadedFile::fake()->image('front.jpg', 600, 400),
        ],
    ], ['Content-Type' => 'multipart/form-data'])->assertOk();

    $frontPath = $distributor->fresh()->national_id_photo_front;

    expect($frontPath)->not->toBeNull();
    Storage::disk('private')->assertExists($frontPath);

    $this->get(route('distributors.documents', ['distributor' => $distributor->id, 'type' => 'front']))
        ->assertOk();
});

it('returns 404 when a document does not exist or the type is invalid', function () {
    $distributor = makeDistributor();

    Sanctum::actingAs(createUser('admin', ['distributors.view']), ['*']);

    $this->get(route('distributors.documents', ['distributor' => $distributor->id, 'type' => 'back']))
        ->assertStatus(404);

    $this->get(route('distributors.documents', ['distributor' => $distributor->id, 'type' => 'side']))
        ->assertStatus(404);
});

it('lets a distributor view their own documents', function () {
    Storage::fake('private');

    $distributor = makeDistributor(['name' => 'صاحب الوثائق']);
    Storage::disk('private')->put('distributor-documents/front.jpg', 'fake-content');
    $distributor->update(['national_id_photo_front' => 'distributor-documents/front.jpg']);

    Sanctum::actingAs($distributor->user, ['*']);

    $this->get(route('distributors.documents', ['distributor' => $distributor->id, 'type' => 'front']))
        ->assertOk();
});

it('forbids another distributor from viewing documents', function () {
    $owner = makeDistributor(['name' => 'صاحب الوثائق']);
    $owner->update(['national_id_photo_front' => 'distributor-documents/front.jpg']);

    $other = makeDistributor(['name' => 'موزع آخر']);
    Sanctum::actingAs($other->user, ['*']);

    $this->get(route('distributors.documents', ['distributor' => $owner->id, 'type' => 'front']))
        ->assertForbidden();
});
