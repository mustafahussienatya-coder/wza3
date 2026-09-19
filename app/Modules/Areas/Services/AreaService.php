<?php

namespace App\Modules\Areas\Services;

use App\Modules\Areas\Exceptions\AreaInUseException;
use App\Modules\Areas\Models\Area;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AreaService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Area::query();

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    public function create(array $data): Area
    {
        return DB::transaction(fn () => Area::create($data));
    }

    public function update(Area $area, array $data): Area
    {
        return DB::transaction(function () use ($area, $data) {
            $area->update($data);

            return $area->fresh();
        });
    }

    public function delete(Area $area): void
    {
        DB::transaction(function () use ($area) {
            if ($area->distributors()->exists()) {
                throw new AreaInUseException;
            }

            $area->delete();
        });
    }
}
