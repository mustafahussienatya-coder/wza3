<?php

namespace App\Modules\Settlements\Services;

use App\Enums\UserRole;
use App\Modules\Settlements\Models\DistributorSettlement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SettlementService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = DistributorSettlement::query()
            ->with(['distributor.user', 'creator']);

        $this->applyRoleScope($query);
        $this->applySearch($query, $filters);
        $this->applyFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getOne(DistributorSettlement $settlement): DistributorSettlement
    {
        return $settlement->load(['distributor.user', 'creator']);
    }

    private function applyRoleScope(Builder $query): void
    {
        $user = auth()->user();

        if ($user !== null && $user->hasRole(UserRole::DISTRIBUTOR->value)) {
            $query->where('distributor_id', $user->distributor?->id);
        }
    }

    private function applySearch(Builder $query, array $filters): void
    {
        if (($filters['search'] ?? '') !== '') {
            $query->where('settlement_number', 'like', '%'.$filters['search'].'%');
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['distributor_id']) && $filters['distributor_id'] !== '') {
            $query->where('distributor_id', $filters['distributor_id']);
        }

        if (isset($filters['payment_method']) && $filters['payment_method'] !== '') {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (isset($filters['from']) && $filters['from'] !== '') {
            $query->whereDate('settlement_date', '>=', $filters['from']);
        }

        if (isset($filters['to']) && $filters['to'] !== '') {
            $query->whereDate('settlement_date', '<=', $filters['to']);
        }
    }
}
