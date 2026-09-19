<?php

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Requests\AdjustOpeningBalanceRequest;
use App\Modules\Customers\Requests\ChangeCustomerStatusRequest;
use App\Modules\Customers\Requests\StoreCustomerRequest;
use App\Modules\Customers\Requests\TransferCustomerRequest;
use App\Modules\Customers\Requests\UpdateCustomerRequest;
use App\Modules\Customers\Resources\CustomerLedgerEntryResource;
use App\Modules\Customers\Resources\CustomerResource;
use App\Modules\Customers\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends BaseController
{
    public function __construct(
        private readonly CustomerService $customerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->customerService->getAll($request->query());
        $customers->through(fn (Customer $customer) => new CustomerResource($customer));

        return $this->paginatedResponse(
            $customers,
            __('customer_messages.customers_retrieved')
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customerService->create($request->validated(), $request->user());

        activity('customers')
            ->performedOn($customer)
            ->event('created')
            ->withProperties($request->safe()->all())
            ->log('Customer created');

        return $this->createdResponse(
            new CustomerResource($customer),
            __('customer_messages.customer_created_successfully')
        );
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $customer->loadMissing(['area', 'distributor.user', 'creator']);

        return $this->resourceResponse(
            new CustomerResource($customer),
            __('customer_messages.customer_retrieved')
        );
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $oldLimit = $customer->credit_limit;

        $customer = $this->customerService->update($customer, $request->validated());

        activity('customers')
            ->performedOn($customer)
            ->event('updated')
            ->withProperties($request->safe()->all())
            ->log('Customer updated');

        if ((float) $oldLimit !== (float) $customer->credit_limit) {
            activity('customers')
                ->performedOn($customer)
                ->event('credit_limit_changed')
                ->withProperties([
                    'old_limit' => $oldLimit,
                    'new_limit' => $customer->credit_limit,
                ])
                ->log('Credit limit changed');
        }

        return $this->successResponse(
            new CustomerResource($customer),
            __('customer_messages.customer_updated_successfully')
        );
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $this->customerService->delete($customer);

        activity('customers')
            ->performedOn($customer)
            ->event('deleted')
            ->log('Customer deleted');

        return $this->noContentResponse(__('customer_messages.customer_deleted_successfully'));
    }

    public function transfer(TransferCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('transfer', $customer);

        $customer = $this->customerService->transfer(
            $customer,
            (int) $request->validated('to_distributor_id'),
            $request->validated('reason'),
            $request->user()
        );

        activity('customers')
            ->performedOn($customer)
            ->event('transferred')
            ->withProperties([
                'to_distributor_id' => $request->validated('to_distributor_id'),
                'reason' => $request->validated('reason'),
            ])
            ->log('Customer transferred');

        return $this->successResponse(
            new CustomerResource($customer),
            __('customer_messages.customer_transferred_successfully')
        );
    }

    public function changeStatus(ChangeCustomerStatusRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('changeStatus', $customer);

        $customer = $this->customerService->changeStatus(
            $customer,
            $request->validated('status')
        );

        activity('customers')
            ->performedOn($customer)
            ->event('status_changed')
            ->withProperties(['status' => $request->validated('status')])
            ->log('Customer status changed');

        return $this->successResponse(
            new CustomerResource($customer),
            __('customer_messages.customer_status_updated_successfully')
        );
    }

    public function adjustOpeningBalance(AdjustOpeningBalanceRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('adjustOpeningBalance', $customer);

        $entry = $this->customerService->adjustOpeningBalance(
            $customer,
            $request->validated(),
            $request->user()
        );

        activity('customers')
            ->performedOn($customer)
            ->event('opening_balance_adjusted')
            ->withProperties($request->safe()->all())
            ->log('Opening balance adjusted');

        return $this->successResponse(
            new CustomerLedgerEntryResource($entry->load('user')),
            __('customer_messages.opening_balance_adjusted_successfully')
        );
    }

    public function ledger(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('viewLedger', $customer);

        $entries = $this->customerService->getLedger($customer, $request->query());
        $entries->through(fn ($entry) => new CustomerLedgerEntryResource($entry));

        return $this->paginatedResponse(
            $entries,
            __('customer_messages.ledger_retrieved')
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewSummary', Customer::class);

        return $this->successResponse(
            $this->customerService->getSummary($request->user()),
            __('customer_messages.summary_retrieved')
        );
    }
}
