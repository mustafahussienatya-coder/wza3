<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerLedgerEntry;
use App\Modules\Invoices\Models\Invoice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function collectionPayload(array $data, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $data['companyCustomer']->id,
        'amount' => '10000',
        'payment_method' => 'cash',
        'collection_date' => '2026-09-17',
        'reference_no' => null,
        'notes' => null,
    ], $overrides);
}

function confirmedCompanySale(array $data, $test): array
{
    $user = createSalesAdmin();
    Sanctum::actingAs($user, ['*']);

    $invoice = Invoice::find($test->postJson('/api/v1/invoices', companyInvoicePayload($data, ['confirm' => true]))
        ->assertCreated()
        ->json('data.id'));

    return ['invoice' => $invoice, 'user' => $user];
}

it('orders the customer ledger newest first', function () {
    $data = salesDataset();
    stockCompany($data);
    confirmedCompanySale($data, $this);

    $this->postJson('/api/v1/collections', collectionPayload($data, ['amount' => '3000']))
        ->assertCreated();

    $this->getJson('/api/v1/customers/'.$data['companyCustomer']->id.'/ledger')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'payment')
        ->assertJsonPath('data.1.type', 'sale')
        ->assertJsonPath('data.0.type_label', 'Payment')
        ->assertJsonPath('data.1.type_label', 'Sale');
});

it('records a collection with a sequential PY- number and a ledger credit', function () {
    $data = salesDataset();
    stockCompany($data);

    confirmedCompanySale($data, $this);

    $collectionId = $this->postJson('/api/v1/collections', collectionPayload($data, ['amount' => '12000']))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.collection_number', 'PY-00001')
        ->assertJsonPath('data.ownership', 'company')
        ->assertJsonPath('data.payment_method', 'cash')
        ->assertJsonPath('data.amount', '12000.00')
        ->assertJsonPath('data.customer.id', $data['companyCustomer']->id)
        ->json('data.id');

    $ledger = CustomerLedgerEntry::query()->where('type', 'payment')->first();

    expect($ledger)->not->toBeNull()
        ->and((float) $ledger->credit)->toBe(12000.0)
        ->and((float) $ledger->balance_after)->toBe(19375.0);

    $this->getJson('/api/v1/collections/'.$collectionId)
        ->assertOk()
        ->assertJsonPath('data.collection_number', 'PY-00001');
});

it('increments the collection number', function () {
    $data = salesDataset();
    stockCompany($data);
    confirmedCompanySale($data, $this);

    $this->postJson('/api/v1/collections', collectionPayload($data, ['amount' => '5000']))
        ->assertCreated()
        ->assertJsonPath('data.collection_number', 'PY-00001');

    $this->postJson('/api/v1/collections', collectionPayload($data, ['amount' => '5000']))
        ->assertCreated()
        ->assertJsonPath('data.collection_number', 'PY-00002');
});

it('marks an invoice as fully paid when the outstanding is settled', function () {
    $data = salesDataset();
    stockCompany($data);
    $confirmed = confirmedCompanySale($data, $this);
    $id = $confirmed['invoice']->id;

    $this->postJson('/api/v1/collections', collectionPayload($data, ['amount' => '31375']))
        ->assertCreated()
        ->assertJsonPath('data.collection_number', 'PY-00001');

    $this->getJson("/api/v1/invoices/{$id}")
        ->assertOk()
        ->assertJsonPath('data.paid_status', 'paid');
});

it('rejects an amount exceeding the outstanding balance', function () {
    $data = salesDataset();
    stockCompany($data);
    confirmedCompanySale($data, $this);

    $this->postJson('/api/v1/collections', collectionPayload($data, ['amount' => '50000']))
        ->assertStatus(409)
        ->assertJsonPath('error_code', 'PAYMENT_EXCEEDS_OUTSTANDING');
});

it('forbids a collection without payments.create permission', function () {
    $data = salesDataset();
    stockCompany($data);
    confirmedCompanySale($data, $this);

    $user = createSalesAdmin('customer_service');
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/collections', collectionPayload($data, ['amount' => '1000']))
        ->assertForbidden();
});

it('rejects collecting for a customer that is not owned by the distributor', function () {
    $data = salesDataset();
    stockCompany($data);
    confirmedCompanySale($data, $this);

    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/collections', array_merge(collectionPayload($data), [
        'customer_id' => $data['companyCustomer']->id,
    ]))
        ->assertForbidden()
        ->assertJsonPath('error_code', 'CUSTOMER_NOT_OWNED');
});

it('rejects collecting for an inactive customer', function () {
    $data = salesDataset();
    stockCompany($data);
    confirmedCompanySale($data, $this);

    Customer::whereKey($data['companyCustomer']->id)->update(['status' => 'inactive']);

    $this->postJson('/api/v1/collections', collectionPayload($data, ['amount' => '1000']))
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'INVALID_COLLECTION_OPERATION');
});

it('lets a distributor collect from their own customer', function () {
    $data = salesDataset();
    disbursePricedCustody($data, createSalesAdmin());
    $user = $data['distributor']->user;
    $user->refresh();

    Sanctum::actingAs($user, ['*']);
    $this->postJson('/api/v1/invoices', distributorInvoicePayload($data))->assertCreated();
    $id = Invoice::orderByDesc('id')->value('id');
    $this->postJson("/api/v1/invoices/{$id}/confirm")->assertOk();

    $this->postJson('/api/v1/collections', [
        'customer_id' => $data['customer']->id,
        'amount' => '10000',
        'payment_method' => 'transfer',
        'collection_date' => '2026-09-17',
    ])
        ->assertCreated()
        ->assertJsonPath('data.collection_number', 'PY-00001')
        ->assertJsonPath('data.ownership', 'distributor')
        ->assertJsonPath('data.distributor.id', $data['distributor']->id);
});

it('lets a distributor see only their own collections', function () {
    $data = salesDataset();
    $other = salesDataset();
    stockCompany($data);
    stockCompany($other);
    confirmedCompanySale($data, $this);

    disbursePricedCustody($data, createSalesAdmin());
    disbursePricedCustody($other, createSalesAdmin());

    $user = $data['distributor']->user;
    $user->refresh();
    Sanctum::actingAs($user, ['*']);
    $this->postJson('/api/v1/distributor/my/invoices', distributorInvoicePayload($data))->assertCreated();
    $ownId = Invoice::orderByDesc('id')->value('id');
    $this->postJson("/api/v1/invoices/{$ownId}/confirm")->assertOk();
    $this->postJson('/api/v1/collections', ['customer_id' => $data['customer']->id, 'amount' => '5000', 'payment_method' => 'cash'])
        ->assertCreated();

    $otherUser = $other['distributor']->user;
    $otherUser->refresh();
    Sanctum::actingAs($otherUser, ['*']);
    $this->postJson('/api/v1/distributor/my/invoices', distributorInvoicePayload($other))->assertCreated();
    $otherId = Invoice::orderByDesc('id')->value('id');
    $this->postJson("/api/v1/invoices/{$otherId}/confirm")->assertOk();
    $this->postJson('/api/v1/collections', ['customer_id' => $other['customer']->id, 'amount' => '3000', 'payment_method' => 'cash'])
        ->assertCreated();

    Sanctum::actingAs($user, ['*']);
    $rows = $this->getJson('/api/v1/collections')->assertOk()->json('data');

    expect(count($rows))->toBe(1)
        ->and($rows[0]['collection_number'])->toBe('PY-00001');

    $rows = $this->getJson('/api/v1/distributor/my/collections')->assertOk()->json('data');

    expect(count($rows))->toBe(1);
});
