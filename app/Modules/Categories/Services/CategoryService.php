<?php

namespace App\Modules\Categories\Services;

use App\Modules\Categories\Exceptions\CategoryCannotDeleteException;
use App\Modules\Categories\Exceptions\CategoryHasChildrenException;
use App\Modules\Categories\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Category::query()->with(['children', 'parent']);

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

        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        if (isset($filters['root']) && $filters['root']) {
            $query->whereNull('parent_id');
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('id', 'asc')->paginate($perPage);
    }

    public function create(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = $data['code'] ?? Category::generateCode();
            $data['status'] = $data['status'] ?? 'active';

            return Category::create($data);
        });
    }

    public function update(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            if (isset($data['parent_id'])) {
                $this->ensureValidParent($category, (int) $data['parent_id']);
            }

            $category->update($data);

            return $category->fresh(['children', 'parent']);
        });
    }

    public function delete(Category $category): void
    {
        DB::transaction(function () use ($category) {
            if ($category->children()->exists()) {
                throw new CategoryHasChildrenException;
            }

            $category->delete();
        });
    }

    private function ensureValidParent(Category $category, int $parentId): void
    {
        if ($category->id === $parentId) {
            throw new CategoryCannotDeleteException;
        }

        $parent = Category::find($parentId);
        if ($parent === null) {
            return;
        }

        $node = $parent;
        while ($node !== null) {
            if ($node->parent_id === $category->id || $node->id === $category->id) {
                throw new CategoryCannotDeleteException;
            }

            $node = Category::find($node->parent_id);
        }
    }
}
