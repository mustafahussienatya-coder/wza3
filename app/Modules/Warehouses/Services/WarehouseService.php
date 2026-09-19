<?php

namespace App\Modules\Warehouses\Services;

use App\Modules\Warehouses\Enums\WarehouseStatus;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class WarehouseService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Warehouse::query()->with('manager');

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function create(array $data): Warehouse
    {
        $data['code'] = $data['code'] ?? Warehouse::generateCode();
        $data['status'] = $data['status'] ?? WarehouseStatus::ACTIVE->value;

        return Warehouse::create($data)->load('manager');
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);

        return $warehouse->fresh('manager');
    }

    public function delete(Warehouse $warehouse): void
    {
        $warehouse->delete();
    }
}
