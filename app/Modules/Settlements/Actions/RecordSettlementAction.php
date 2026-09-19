<?php

namespace App\Modules\Settlements\Actions;

use App\Enums\UserRole;
use App\Modules\Collections\Models\Collection;
use App\Modules\Distributors\Models\Distributor;
use App\Modules\Invoices\Services\DocumentCounterService;
use App\Modules\Settlements\Exceptions\InvalidSettlementOperationException;
use App\Modules\Settlements\Exceptions\SettlementExceedsCollectedException;
use App\Modules\Settlements\Models\DistributorSettlement;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class RecordSettlementAction
{
    public function __construct(
        private readonly DocumentCounterService $documentCounterService,
    ) {}

    public function execute(array $data, User $user): DistributorSettlement
    {
        return DB::transaction(function () use ($data, $user) {
            $distributorId = $this->resolveDistributorId($data, $user);

            $distributor = Distributor::query()
                ->whereKey($distributorId)
                ->lockForUpdate()
                ->first();

            if ($distributor === null || ! $distributor->isActive()) {
                throw new InvalidSettlementOperationException;
            }

            $amount = bcadd((string) $data['amount'], '0', 2);

            if (bccomp($amount, '0', 2) <= 0) {
                throw new InvalidSettlementOperationException;
            }

            $this->assertNotExceedingCollectedCash($distributorId, $amount);

            $settlementNumber = $this->documentCounterService->next(DistributorSettlement::DOC_TYPE, 'ST-', 5);

            $settlement = DistributorSettlement::create([
                'settlement_number' => $settlementNumber,
                'distributor_id' => $distributorId,
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'reference_no' => $data['reference_no'] ?? null,
                'settlement_date' => $data['settlement_date'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            activity('settlements')
                ->performedOn($settlement)
                ->event('settlement.created')
                ->withProperties([
                    'amount' => $amount,
                    'payment_method' => $data['payment_method'],
                ])
                ->log('Settlement recorded');

            return $settlement->fresh(['distributor.user', 'creator']);
        });
    }

    private function resolveDistributorId(array $data, User $user): int
    {
        if ($user->hasRole(UserRole::DISTRIBUTOR->value)) {
            $distributorId = $user->distributor?->id;
        } else {
            $distributorId = $data['distributor_id'] ?? null;
        }

        if ($distributorId === null) {
            throw new InvalidSettlementOperationException;
        }

        return (int) $distributorId;
    }

    private function assertNotExceedingCollectedCash(int $distributorId, string $amount): void
    {
        $collected = Collection::query()
            ->where('distributor_id', $distributorId)
            ->sum('amount');

        $settled = DistributorSettlement::query()
            ->where('distributor_id', $distributorId)
            ->sum('amount');

        $collectedUnsettled = bcsub((string) $collected, (string) $settled, 2);

        if (bccomp($amount, $collectedUnsettled, 2) > 0) {
            throw new SettlementExceedsCollectedException;
        }
    }
}
