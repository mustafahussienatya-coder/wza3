<?php

namespace App\Modules\Collections\Actions;

use App\Enums\UserRole;
use App\Modules\Collections\Exceptions\CustomerNotOwnedException;
use App\Modules\Collections\Exceptions\InvalidCollectionOperationException;
use App\Modules\Collections\Exceptions\PaymentExceedsOutstandingException;
use App\Modules\Collections\Models\Collection;
use App\Modules\Customers\Enums\LedgerTransactionType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Invoices\Services\DocumentCounterService;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class RecordCollectionAction
{
    public function __construct(
        private readonly DocumentCounterService $documentCounterService,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function execute(array $data, User $user): Collection
    {
        return DB::transaction(function () use ($data, $user) {
            $customer = Customer::query()
                ->whereKey($data['customer_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $isDistributor = $user->hasRole(UserRole::DISTRIBUTOR->value);
            $distributorId = $isDistributor ? $user->distributor?->id : null;

            if ($customer->distributor_id !== $distributorId) {
                throw new CustomerNotOwnedException;
            }

            if (! $customer->isActive()) {
                throw new InvalidCollectionOperationException;
            }

            $amount = bcadd((string) $data['amount'], '0', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                throw new InvalidCollectionOperationException;
            }

            $outstanding = $this->invoiceService->getOutstandingBalance($customer->id);

            if (bccomp($amount, $outstanding, 2) > 0) {
                throw new PaymentExceedsOutstandingException;
            }

            $collectionNumber = $this->documentCounterService->next(Collection::DOC_TYPE, 'PY-', 5);

            $collection = Collection::create([
                'collection_number' => $collectionNumber,
                'distributor_id' => $distributorId,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'reference_no' => $data['reference_no'] ?? null,
                'collection_date' => $data['collection_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->invoiceService->createLedgerEntry(
                $customer->id,
                LedgerTransactionType::PAYMENT,
                '0',
                $amount,
                $user->id,
                $collectionNumber,
            );

            return $collection->fresh(['customer', 'distributor.user', 'creator']);
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, Collection>
     */
    public function executeMany(array $collections, User $user): \Illuminate\Support\Collection
    {
        $created = collect();

        foreach ($collections as $data) {
            $created->push($this->execute($data, $user));
        }

        return $created;
    }
}
