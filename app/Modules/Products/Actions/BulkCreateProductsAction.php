<?php

namespace App\Modules\Products\Actions;

use App\Modules\Categories\Enums\CategoryStatus;
use App\Modules\Categories\Models\Category;
use App\Modules\Inventory\Actions\AddStockInAction;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Exceptions\InvalidBulkRowException;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductService;
use App\Modules\Units\Models\Unit;
use App\Modules\Warehouses\Enums\WarehouseStatus;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class BulkCreateProductsAction
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly AddStockInAction $addStockInAction,
    ) {}

    /**
     * جزئي النجاح: كل صف مستقل؛ الصفو الخطأ يُتخطّى بخطئه والصفوف الصحيحة تُحفظ.
     *
     * @param  array<int, array{
     *     name: string,
     *     category_id: int,
     *     base_unit_id: int,
     *     min_stock_level?: string|int|float,
     *     status?: string,
     *     opening?: array{
     *         warehouse_id: int,
     *         quantity: string|int|float,
     *         unit_price: string|int|float,
     *     },
     * }>  $items
     * @return array{created: array<int, Product>, errors: array<int, array{index: int, name: string, error_code: string, message: string}>}
     */
    public function execute(array $items, int $userId): array
    {
        $created = [];
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                $product = DB::transaction(fn () => $this->processRow($item, $userId));
                $created[] = $product;
            } catch (InvalidBulkRowException $e) {
                $errors[] = [
                    'index' => $index,
                    'name' => (string) ($item['name'] ?? ''),
                    'error_code' => $e->errorCode,
                    'message' => __('product_messages.bulk_errors.'.$e->errorCode),
                ];
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    private function processRow(array $item, int $userId): Product
    {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            throw new InvalidBulkRowException('NAME_REQUIRED');
        }

        $category = Category::query()
            ->whereKey($item['category_id'] ?? null)
            ->where('status', CategoryStatus::ACTIVE)
            ->first();

        if ($category === null) {
            throw new InvalidBulkRowException('CATEGORY_INVALID');
        }

        $baseUnit = Unit::query()
            ->whereKey($item['base_unit_id'] ?? null)
            ->where('is_active', true)
            ->first();

        if ($baseUnit === null) {
            throw new InvalidBulkRowException('BASE_UNIT_INVALID');
        }

        $status = $item['status'] ?? ProductStatus::ACTIVE->value;
        if (! in_array($status, [ProductStatus::ACTIVE->value, ProductStatus::INACTIVE->value], true)) {
            throw new InvalidBulkRowException('STATUS_INVALID');
        }

        $minStockLevel = $item['min_stock_level'] ?? 0;
        if (! is_numeric($minStockLevel) || $minStockLevel < 0) {
            throw new InvalidBulkRowException('MIN_STOCK_INVALID');
        }

        $product = $this->productService->create([
            'name' => $name,
            'category_id' => (int) $item['category_id'],
            'base_unit_id' => (int) $item['base_unit_id'],
            'min_stock_level' => $minStockLevel,
            'status' => $status,
            'units' => [],
        ]);

        if (! empty($item['opening'])) {
            $this->addOpeningBalance($product, $item['opening'], $userId);
        }

        return $product;
    }

    private function addOpeningBalance(Product $product, array $opening, int $userId): void
    {
        $warehouse = Warehouse::query()
            ->whereKey($opening['warehouse_id'] ?? null)
            ->where('status', WarehouseStatus::ACTIVE)
            ->first();

        if ($warehouse === null) {
            throw new InvalidBulkRowException('OPENING_WAREHOUSE_INVALID');
        }

        $quantity = $opening['quantity'] ?? null;
        if (! is_numeric($quantity) || $quantity <= 0) {
            throw new InvalidBulkRowException('OPENING_QUANTITY_INVALID');
        }

        $unitPrice = $opening['unit_price'] ?? null;
        if (! is_numeric($unitPrice) || $unitPrice < 0) {
            throw new InvalidBulkRowException('OPENING_PRICE_REQUIRED');
        }

        $this->addStockInAction->execute(
            productId: $product->id,
            warehouseId: (int) $warehouse->id,
            unitId: (int) $product->base_unit_id,
            quantity: (string) $quantity,
            unitPrice: (string) $unitPrice,
            reason: StockMovementReason::OPENING_BALANCE,
            userId: $userId,
        );
    }
}
