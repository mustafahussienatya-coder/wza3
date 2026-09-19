<?php

namespace App\Modules\Units\Services;

use App\Modules\Units\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UnitService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Unit::query();

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['is_weight'])) {
            $query->where('is_weight', filter_var($filters['is_weight'], FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function create(array $data): Unit
    {
        $data['decimal_places'] = $data['decimal_places'] ?? 0;
        $data['is_weight'] = $data['is_weight'] ?? false;
        $data['is_active'] = $data['is_active'] ?? true;

        return Unit::create($data);
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($data);

        return $unit->fresh();
    }

    public function delete(Unit $unit): void
    {
        $unit->delete();
    }
}
