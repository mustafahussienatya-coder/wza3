<?php

namespace App\Modules\Distributors\Services;

use App\Enums\UserRole;
use App\Modules\Distributors\Enums\CustodyMovementType;
use App\Modules\Distributors\Enums\DistributorIssueStatus;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustodyService
{
    public function getInventory(int $distributorId, array $filters): LengthAwarePaginator
    {
        $query = DistributorInventory::query()
            ->where('distributor_id', $distributorId)
            ->with(['product.baseUnit']);

        if (! empty($filters['search'])) {
            $query->whereHas('product', function (Builder $q) use ($filters) {
                return $q->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('code', 'like', '%'.$filters['search'].'%');
            });
        }

        if (isset($filters['product_id']) && $filters['product_id'] !== '') {
            $query->where('product_id', $filters['product_id']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('updated_at', 'desc')->paginate($perPage);
    }

    public function getSellableProducts(int $distributorId): Collection
    {
        $inventories = DistributorInventory::query()
            ->where('distributor_id', $distributorId)
            ->where('quantity', '>', 0)
            ->with(['product.baseUnit', 'product.activeUnits.unit'])
            ->get();

        $productIds = $inventories->pluck('product_id');

        $basePrices = [];
        if ($productIds->isNotEmpty()) {
            CustodyBatch::query()
                ->where('distributor_id', $distributorId)
                ->whereIn('product_id', $productIds)
                ->where('remaining', '>', 0)
                ->orderBy('issued_at')
                ->orderBy('id')
                ->get(['id', 'product_id', 'unit_price'])
                ->each(function (CustodyBatch $batch) use (&$basePrices) {
                    if (! isset($basePrices[$batch->product_id])) {
                        $basePrices[$batch->product_id] = (string) $batch->unit_price;
                    }
                });
        }

        foreach ($inventories as $inventory) {
            $inventory->setAttribute(
                'custody_unit_price',
                $basePrices[$inventory->product_id] ?? null
            );
        }

        return $inventories;
    }

    public function getStatement(int $distributorId, array $filters): LengthAwarePaginator
    {
        $query = CustodyMovement::query()
            ->where('distributor_id', $distributorId)
            ->with(['product.baseUnit', 'unit', 'performedBy', 'referenceIssue:id,issue_number'])
            ->addSelect('custody_movements.*')
            ->addSelect(DB::raw('SUM(base_quantity) OVER (ORDER BY created_at ASC, id ASC) AS running_base_quantity'));

        if (! empty($filters['movement_type']) && in_array($filters['movement_type'], CustodyMovementType::toArray(), true)) {
            $query->where('movement_type', $filters['movement_type']);
        }

        if (isset($filters['product_id']) && $filters['product_id'] !== '') {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['from'])) {
            $from = CarbonImmutable::parse($filters['from'])->utc();
            $query->where('created_at', '>=', $from);
        }

        if (! empty($filters['to'])) {
            $to = CarbonImmutable::parse($filters['to'])->utc();
            $query->where('created_at', '<=', $to);
        }

        if (! empty($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->whereHas('product', function (Builder $p) use ($filters) {
                    $p->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('code', 'like', '%'.$filters['search'].'%');
                })->orWhereHas('referenceIssue', function (Builder $r) use ($filters) {
                    $r->where('issue_number', 'like', '%'.$filters['search'].'%');
                });
            });
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate($perPage);
    }

    public function listIssues(array $filters, ?User $user = null): LengthAwarePaginator
    {
        $query = DistributorIssue::query()
            ->with(['distributor.user', 'warehouse', 'creator', 'items.product.baseUnit', 'items.unit']);

        if ($user && $user->hasRole(UserRole::DISTRIBUTOR->value)) {
            $query->whereHas('distributor', function (Builder $q) use ($user) {
                return $q->where('user_id', $user->id);
            });
        }

        if (! empty($filters['status']) && in_array($filters['status'], DistributorIssueStatus::toArray(), true)) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['distributor_id']) && $filters['distributor_id'] !== '') {
            $query->where('distributor_id', $filters['distributor_id']);
        }

        if (! empty($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('distributor_issues.issue_number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('distributor.user', function (Builder $u) use ($filters) {
                        return $u->where('name', 'like', '%'.$filters['search'].'%');
                    });
            });
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }

    public function showIssue(int $id): DistributorIssue
    {
        return DistributorIssue::query()
            ->with([
                'distributor.user',
                'warehouse',
                'creator',
                'approver',
                'completer',
                'items.product.baseUnit',
                'items.unit',
                'corrections.performedBy',
                'corrections.items.product',
                'corrections.items.originalUnit',
                'corrections.items.correctedUnit',
            ])
            ->findOrFail($id);
    }
}
