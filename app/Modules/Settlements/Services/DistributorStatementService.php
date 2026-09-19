<?php

namespace App\Modules\Settlements\Services;

use App\Modules\Collections\Models\Collection;
use App\Modules\Distributors\Enums\CustodyMovementType;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Settlements\Models\DistributorSettlement;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection as SupportCollection;

class DistributorStatementService
{
    private const IN_TYPES = [
        CustodyMovementType::ISSUE->value,
        CustodyMovementType::CUSTOMER_RETURN->value,
        CustodyMovementType::TRANSFER_IN->value,
    ];

    private const OUT_TYPES = [
        CustodyMovementType::SALE->value,
        CustodyMovementType::RETURN_TO_WAREHOUSE->value,
        CustodyMovementType::TRANSFER_OUT->value,
    ];

    public function getStatement(int $distributorId, array $filters): LengthAwarePaginator
    {
        $entries = $this->custodyEntries($distributorId)
            ->concat($this->collectionEntries($distributorId))
            ->concat($this->settlementEntries($distributorId))
            ->sortBy([
                ['sort_at', 'asc'],
                ['sort_rank', 'asc'],
                ['sort_id', 'asc'],
            ])
            ->values();

        $balance = '0.00';

        $entries = $entries->map(function (array $entry) use (&$balance): array {
            $balance = bcsub(bcadd($balance, $entry['debit'], 2), $entry['credit'], 2);
            $entry['running_balance'] = $balance;

            return $entry;
        });

        $entries = $this->applyDateRange($entries, $filters)
            ->reverse()
            ->values();

        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return new Paginator(
            $entries->forPage($page, $perPage)->values()->all(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function custodyEntries(int $distributorId): SupportCollection
    {
        $groups = CustodyMovement::query()
            ->where('distributor_id', $distributorId)
            ->whereIn('movement_type', array_merge(self::IN_TYPES, self::OUT_TYPES))
            ->with(['product:id,name', 'referenceIssue:id,issue_number'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('product_id');

        $entries = new SupportCollection;

        foreach ($groups as $productMovements) {
            $queue = [];

            foreach ($productMovements as $movement) {
                $base = (string) $movement->base_quantity;
                $price = (string) $movement->selling_price;
                $isIn = in_array($movement->movement_type->value, self::IN_TYPES, true);

                if ($isIn) {
                    $value = bcmul($base, $price, 2);
                    $debit = bcadd($value, '0', 2);
                    $credit = '0.00';
                    $queue[] = ['remaining' => $base, 'price' => $price];
                } else {
                    $need = $base;
                    $value = '0.00';

                    foreach ($queue as &$layer) {
                        if (bccomp($need, '0', 4) <= 0) {
                            break;
                        }

                        $take = bccomp($layer['remaining'], $need, 4) >= 0
                            ? $need
                            : $layer['remaining'];

                        $value = bcadd($value, bcmul($take, $layer['price'], 2), 2);
                        $layer['remaining'] = bcsub($layer['remaining'], $take, 4);
                        $need = bcsub($need, $take, 4);
                    }
                    unset($layer);

                    $debit = '0.00';
                    $credit = bcadd($value, '0', 2);
                }

                $reference = $movement->reference_type === DistributorIssue::MOVEMENT_REFERENCE_TYPE
                    ? $movement->referenceIssue?->issue_number
                    : null;

                $entries->push([
                    'id' => 'custody-'.$movement->id,
                    'entry_type' => $movement->movement_type->value,
                    'occurred_at' => $movement->created_at?->toIso8601String(),
                    'sort_at' => $movement->created_at?->format('Y-m-d H:i:s.u'),
                    'sort_rank' => 1,
                    'sort_id' => (int) $movement->id,
                    'product_id' => $movement->product_id,
                    'product_name' => $movement->product?->name,
                    'party_name' => null,
                    'quantity' => bcadd($base, '0', 4),
                    'reference' => $reference,
                    'debit' => $debit,
                    'credit' => $credit,
                ]);
            }
        }

        return $entries;
    }

    private function collectionEntries(int $distributorId): SupportCollection
    {
        return Collection::query()
            ->where('distributor_id', $distributorId)
            ->with('customer:id,name')
            ->get()
            ->map(fn (Collection $collection): array => [
                'id' => 'collection-'.$collection->id,
                'entry_type' => 'collection',
                'occurred_at' => $collection->created_at?->toIso8601String(),
                'sort_at' => $collection->created_at?->format('Y-m-d H:i:s.u'),
                'sort_rank' => 2,
                'sort_id' => (int) $collection->id,
                'product_id' => null,
                'product_name' => null,
                'party_name' => $collection->customer?->name,
                'quantity' => null,
                'reference' => $collection->collection_number,
                'debit' => bcadd((string) $collection->amount, '0', 2),
                'credit' => '0.00',
            ]);
    }

    private function settlementEntries(int $distributorId): SupportCollection
    {
        return DistributorSettlement::query()
            ->where('distributor_id', $distributorId)
            ->get()
            ->map(fn (DistributorSettlement $settlement): array => [
                'id' => 'settlement-'.$settlement->id,
                'entry_type' => 'settlement',
                'occurred_at' => $settlement->created_at?->toIso8601String(),
                'sort_at' => $settlement->created_at?->format('Y-m-d H:i:s.u'),
                'sort_rank' => 3,
                'sort_id' => (int) $settlement->id,
                'product_id' => null,
                'product_name' => null,
                'party_name' => null,
                'quantity' => null,
                'reference' => $settlement->settlement_number,
                'debit' => '0.00',
                'credit' => bcadd((string) $settlement->amount, '0', 2),
            ]);
    }

    private function applyDateRange(SupportCollection $entries, array $filters): SupportCollection
    {
        if (! empty($filters['from'])) {
            $from = CarbonImmutable::parse($filters['from'])->utc();
            $entries = $entries->filter(
                fn (array $entry): bool => CarbonImmutable::parse($entry['occurred_at'])->greaterThanOrEqualTo($from)
            );
        }

        if (! empty($filters['to'])) {
            $to = CarbonImmutable::parse($filters['to'])->utc();
            $entries = $entries->filter(
                fn (array $entry): bool => CarbonImmutable::parse($entry['occurred_at'])->lessThanOrEqualTo($to)
            );
        }

        return $entries->values();
    }
}
