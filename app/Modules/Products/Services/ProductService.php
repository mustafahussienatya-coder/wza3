<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['units.unit', 'category', 'baseUnit'])
            ->withSum('inventories as total_stock', 'quantity');

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhereHas('units', fn (Builder $u) => $u->where('barcode', 'like', "%{$search}%"));
            });
        }

        if (isset($filters['category_id']) && $filters['category_id'] !== '') {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['stock_status']) && in_array($filters['stock_status'], ['normal', 'low', 'out'], true)) {
            $stockExpr = '(SELECT COALESCE(SUM(quantity), 0) FROM inventories WHERE inventories.product_id = products.id)';

            match ($filters['stock_status']) {
                'out' => $query->whereRaw("{$stockExpr} = 0"),
                'low' => $query->whereRaw("{$stockExpr} > 0 AND {$stockExpr} < products.min_stock_level"),
                'normal' => $query->whereRaw("{$stockExpr} >= products.min_stock_level"),
            };
        }

        if (! empty($filters['low_stock'])) {
            $totalExpr = '(SELECT COALESCE(SUM(quantity), 0) FROM inventories WHERE inventories.product_id = products.id)';

            $query->where(function (Builder $q) use ($totalExpr) {
                $q->whereRaw("{$totalExpr} < products.min_stock_level")
                    ->orWhereRaw("{$totalExpr} = 0");
            });
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = $data['code'] ?? Product::generateCode();
            $data['status'] = $data['status'] ?? ProductStatus::ACTIVE->value;
            $data['min_stock_level'] = $data['min_stock_level'] ?? 0;

            $product = Product::create($data);

            $this->syncUnits($product, $data['units'] ?? []);

            return $product->load(['units.unit', 'category', 'baseUnit']);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update(Arr::except($data, ['units']));

            if (array_key_exists('units', $data)) {
                $product->units()->delete();
                $this->syncUnits($product, $data['units']);
            }

            return $product->fresh(['units.unit', 'category', 'baseUnit']);
        });
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->delete();
        });
    }

    private function syncUnits(Product $product, array $units): void
    {
        $rows = array_map(function (array $unit) use ($product) {
            return [
                'product_id' => $product->id,
                'unit_id' => $unit['unit_id'],
                'conversion_factor' => $unit['conversion_factor'],
                'selling_price' => $unit['selling_price'] ?? 0,
                'cost_price' => $unit['cost_price'] ?? 0,
                'barcode' => $unit['barcode'] ?? null,
                'is_active' => $unit['is_active'] ?? true,
            ];
        }, $units);

        $hasBaseRow = collect($rows)->contains('unit_id', $product->base_unit_id);

        if (! $hasBaseRow) {
            $rows[] = [
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'conversion_factor' => 1,
                'selling_price' => 0,
                'cost_price' => 0,
                'barcode' => null,
                'is_active' => true,
            ];
        }

        $product->units()->createMany($rows);
    }
}
