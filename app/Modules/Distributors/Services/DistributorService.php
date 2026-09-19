<?php

namespace App\Modules\Distributors\Services;

use App\Modules\Distributors\Enums\DistributorStatus;
use App\Modules\Distributors\Models\Distributor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DistributorService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Distributor::query()->with(['user', 'areas']);

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->whereHas('user', function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && DistributorStatus::tryFrom($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_active'])) {
            $isActive = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('user', fn (Builder $q) => $q->where('is_active', $isActive));
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function updateStatus(Distributor $distributor, string $status): Distributor
    {
        $distributor->update(['status' => $status]);

        return $distributor->fresh(['user', 'areas']);
    }
}
