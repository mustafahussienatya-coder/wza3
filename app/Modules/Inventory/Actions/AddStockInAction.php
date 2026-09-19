<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InvalidStockOperationException;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductUnit;
use App\Modules\Warehouses\Enums\WarehouseStatus;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AddStockInAction
{
    public function execute(
        int $productId,
        int $warehouseId,
        int $unitId,
        string $quantity,
        string $unitPrice,
        StockMovementReason $reason,
        StockMovementType $type = StockMovementType::STOCK_IN,
        ?Carbon $movedAt = null,
        ?string $description = null,
        ?string $referenceType = null,
        ?string $referenceNo = null,
        int $userId = 0,
    ): StockMovement {
        $product = Product::findOrFail($productId);
        $warehouse = Warehouse::findOrFail($warehouseId);

        $productUnit = ProductUnit::query()
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->first();

        if (! $product->isActive() || $warehouse->status !== WarehouseStatus::ACTIVE || $productUnit === null) {
            throw new InvalidStockOperationException;
        }

        $factor = (string) $productUnit->conversion_factor;
        $baseQuantity = bcmul($quantity, $factor, 4);
        $baseUnitCost = bcdiv($unitPrice, $factor, 2);
        $movingDate = $movedAt ?? Carbon::now();

        return DB::transaction(function () use ($productId, $warehouseId, $unitId, $unitPrice, $reason, $type, $movingDate, $description, $referenceType, $referenceNo, $userId, $factor, $baseQuantity, $baseUnitCost) {
            $inventory = Inventory::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if ($inventory === null) {
                $inventory = Inventory::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => '0',
                ]);
            }

            $movement = StockMovement::create([
                'movement_no' => StockMovement::generateMovementNo(),
                'product_id' => $productId,
                'to_warehouse_id' => $warehouseId,
                'type' => $type,
                'reason' => $reason,
                'quantity' => $baseQuantity,
                'unit_id' => $unitId,
                'conversion_factor' => $factor,
                'unit_price' => $unitPrice,
                'reference_type' => $referenceType,
                'reference_no' => $referenceNo,
                'user_id' => $userId,
                'moved_at' => $movingDate,
                'description' => $description,
            ]);

            StockBatch::create([
                'stock_movement_id' => $movement->id,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_no' => StockBatch::generateBatchNo(),
                'received_at' => $movingDate->toDateTimeString(),
                'quantity' => $baseQuantity,
                'remaining' => $baseQuantity,
                'unit_cost' => $baseUnitCost,
            ]);

            $inventory->update([
                'quantity' => bcadd($inventory->quantity, $baseQuantity, 4),
            ]);

            return $movement->fresh(['product.baseUnit', 'toWarehouse', 'unit', 'user']);
        });
    }
}
