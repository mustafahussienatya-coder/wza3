<?php

use App\Modules\Collections\Models\Collection;
use App\Modules\Customers\Models\CustomerLedgerEntry;
use App\Modules\Distributors\Actions\ReturnDistributorIssueAction;
use App\Modules\Distributors\Models\DistributorIssue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function settlementPayload(int $distributorId, array $overrides = []): array
{
    return array_merge([
        'distributor_id' => $distributorId,
        'amount' => '1000',
        'payment_method' => 'cash',
        'settlement_date' => '2026-09-17',
        'reference_no' => null,
        'notes' => null,
    ], $overrides);
}

function makeCollectedCash(array $data, string $amount = '5000'): void
{
    $customer = makeCustomer($data['distributor']);

    Collection::create([
        'collection_number' => 'PY-'.strtoupper(uniqid()),
        'distributor_id' => $data['distributor']->id,
        'customer_id' => $customer->id,
        'amount' => $amount,
        'payment_method' => 'cash',
        'collection_date' => now(),
        'created_by' => $data['distributor']->user_id,
    ]);
}

it('records a settlement with a sequential ST- number', function () {
    $data = salesDataset();
    makeCollectedCash($data, '5000');
    $admin = createSalesAdmin();

    Sanctum::actingAs($admin, ['*']);

    $id = $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '2000']))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.settlement_number', 'ST-00001')
        ->assertJsonPath('data.amount', '2000.00')
        ->assertJsonPath('data.payment_method', 'cash')
        ->assertJsonPath('data.distributor.id', $data['distributor']->id)
        ->json('data.id');

    $this->getJson('/api/v1/settlements/'.$id)
        ->assertOk()
        ->assertJsonPath('data.settlement_number', 'ST-00001');
});

it('increments the settlement number', function () {
    $data = salesDataset();
    makeCollectedCash($data, '10000');
    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);

    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '1000']))
        ->assertCreated()
        ->assertJsonPath('data.settlement_number', 'ST-00001');

    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '1000']))
        ->assertCreated()
        ->assertJsonPath('data.settlement_number', 'ST-00002');
});

it('does not touch the customer ledger or invoice paid status', function () {
    $data = salesDataset();
    stockCompany($data);
    confirmedCompanySale($data, $this);
    makeCollectedCash($data, '5000');

    $beforeLedgerCount = CustomerLedgerEntry::query()->count();

    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);

    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '2000']))
        ->assertCreated();

    expect(CustomerLedgerEntry::query()->count())->toBe($beforeLedgerCount);
});

it('rejects a settlement exceeding the collected cash not yet settled', function () {
    $data = salesDataset();
    makeCollectedCash($data, '500');
    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);

    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '501']))
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'SETTLEMENT_EXCEEDS_COLLECTED');
});

it('allows a settlement up to the exact collected cash, then blocks the next one', function () {
    $data = salesDataset();
    makeCollectedCash($data, '1000');
    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);

    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '1000']))
        ->assertCreated();

    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '0.01']))
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'SETTLEMENT_EXCEEDS_COLLECTED');
});

it('rejects a zero or negative settlement amount', function () {
    $data = salesDataset();
    makeCollectedCash($data, '5000');
    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);

    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '0']))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'VALIDATION_ERROR');

    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '-50']))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'VALIDATION_ERROR');
});

it('rejects a settlement for a suspended or missing distributor', function () {
    $data = salesDataset();

    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);

    $this->postJson('/api/v1/settlements', settlementPayload(999999, ['amount' => '100']))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'VALIDATION_ERROR');
});

it('forbids a settlement without settlements.create permission', function () {
    $data = salesDataset();
    makeCollectedCash($data, '5000');

    $user = createSalesAdmin('customer_service');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '100']))
        ->assertForbidden();
});

it('lets a distributor record their own settlement', function () {
    $data = salesDataset();
    makeCollectedCash($data, '5000');

    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/distributor/my/settlements', [
        'amount' => '1000',
        'payment_method' => 'transfer',
        'reference_no' => 'TRX-123',
        'settlement_date' => '2026-09-17',
    ])
        ->assertCreated()
        ->assertJsonPath('data.settlement_number', 'ST-00001')
        ->assertJsonPath('data.distributor.id', $data['distributor']->id);
});

it('lets a distributor see only their own settlements', function () {
    $data = salesDataset();
    $other = salesDataset();
    makeCollectedCash($data, '5000');
    makeCollectedCash($other, '5000');

    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);
    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '1000']))
        ->assertCreated();
    $this->postJson('/api/v1/settlements', settlementPayload($other['distributor']->id, ['amount' => '1000']))
        ->assertCreated();

    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $rows = $this->getJson('/api/v1/distributor/my/settlements')->assertOk()->json('data');

    expect(count($rows))->toBe(1)
        ->and($rows[0]['distributor']['id'])->toBe($data['distributor']->id);
});

it('computes the responsibility statement from goods, collections and settlements', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    makeCollectedCash($data, '5000');

    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);
    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '2000']))
        ->assertCreated();

    $statement = $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/responsibility')
        ->assertOk()
        ->json('data');

    expect($statement['goods_value'])->toBe('125500.00')
        ->and($statement['custody_quantity'])->toBe('1000.0000')
        ->and($statement['collected_unsettled'])->toBe('3000.00')
        ->and($statement['total'])->toBe('128500.00')
        ->and(count($statement['per_product']))->toBe(1);
});

it('returns an empty responsibility statement for a distributor with no activity', function () {
    $data = salesDataset();
    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);

    $statement = $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/responsibility')
        ->assertOk()
        ->json('data');

    expect($statement['goods_value'])->toBe('0.00')
        ->and($statement['collected_unsettled'])->toBe('0.00')
        ->and($statement['total'])->toBe('0.00')
        ->and($statement['custody_quantity'])->toBe('0.0000')
        ->and(count($statement['per_product']))->toBe(0);
});

it('lets a distributor see their own responsibility statement', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    makeCollectedCash($data, '5000');

    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $statement = $this->getJson('/api/v1/distributor/my/responsibility')
        ->assertOk()
        ->json('data');

    expect($statement['total'])->toBe('130500.00');
});

it('reduces the responsibility goods value when custody is returned to the warehouse', function () {
    $data = salesDataset();
    $admin = createSalesAdmin();
    disbursePricedCustody($data, $admin, '125.50', '1000');

    $issue = DistributorIssue::query()->firstOrFail();

    app(ReturnDistributorIssueAction::class)->execute($issue, $admin->id);

    Sanctum::actingAs($admin, ['*']);

    $statement = $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/responsibility')
        ->assertOk()
        ->json('data');

    expect($statement['goods_value'])->toBe('0.00')
        ->and($statement['custody_quantity'])->toBe('0.0000');
});

it('returns a distributor statement whose balance matches the responsibility total', function () {
    $data = salesDataset();
    $admin = createSalesAdmin();
    disbursePricedCustody($data, $admin, '125.50', '1000');
    makeCollectedCash($data, '5000');

    Sanctum::actingAs($admin, ['*']);
    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '2000']))
        ->assertCreated();

    $entries = $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/statement')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    $responsibility = $this->getJson('/api/v1/distributors/'.$data['distributor']->id.'/responsibility')
        ->assertOk()
        ->json('data');

    $types = array_column($entries, 'entry_type');

    expect(count($entries))->toBeGreaterThan(0)
        ->and($types)->toContain('issue')
        ->and($types)->toContain('collection')
        ->and($types)->toContain('settlement')
        ->and($entries[0]['running_balance'])->toBe($responsibility['total']);
});

it('lets a distributor view their own statement', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());

    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $entries = $this->getJson('/api/v1/distributor/my/statement')->assertOk()->json('data');
    $responsibility = $this->getJson('/api/v1/distributor/my/responsibility')->assertOk()->json('data');

    expect(count($entries))->toBeGreaterThan(0)
        ->and($entries[0]['running_balance'])->toBe($responsibility['total']);
});

it('returns the settlement list filters by distributor', function () {
    $data = salesDataset();
    $other = salesDataset();
    makeCollectedCash($data, '5000');
    makeCollectedCash($other, '5000');

    $admin = createSalesAdmin();
    Sanctum::actingAs($admin, ['*']);
    $this->postJson('/api/v1/settlements', settlementPayload($data['distributor']->id, ['amount' => '1000']))
        ->assertCreated();
    $this->postJson('/api/v1/settlements', settlementPayload($other['distributor']->id, ['amount' => '1000']))
        ->assertCreated();

    $rows = $this->getJson('/api/v1/settlements?distributor_id='.$data['distributor']->id)
        ->assertOk()
        ->json('data');

    expect(count($rows))->toBe(1)
        ->and($rows[0]['distributor']['id'])->toBe($data['distributor']->id);
});
