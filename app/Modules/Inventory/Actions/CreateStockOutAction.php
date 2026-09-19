<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementLine;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateStockOutAction
{
    public function execute(
        int $productId,
        int $warehouseId,
        string $quantity,
        StockMovementReason $reason,
        StockMovementType $type = StockMovementType::STOCK_OUT,
        ?int $unitId = null,
        ?string $conversionFactor = null,
        ?string $unitPrice = null,
        ?string $referenceType = null,
        ?string $referenceNo = null,
        ?int $distributorId = null,
        int $userId = 0,
        ?Carbon $movedAt = null,
        ?string $description = null,
    ): StockMovement {
        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $reason, $type, $unitId, $conversionFactor, $unitPrice, $referenceType, $referenceNo, $distributorId, $userId, $movedAt, $description) {
            $inventory = Inventory::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            $batches = StockBatch::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('remaining', '>', 0)
                ->orderBy('received_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = $batches->reduce(
                fn (string $carry, StockBatch $batch): string => bcadd($carry, (string) $batch->remaining, 4),
                '0',
            );

            if ($inventory === null || bccomp($available, $quantity, 4) < 0) {
                $product = Product::query()->with('baseUnit')->find($productId);

                throw new InsufficientStockException(
                    product: $product?->name ?? null,
                    available: $inventory !== null ? bcadd($available, '0', 4) : '0',
                    required: $quantity,
                    unit: $product?->baseUnit?->name ?? null,
                );
            }

            $movement = StockMovement::create([
                'movement_no' => StockMovement::generateMovementNo(),
                'product_id' => $productId,
                'from_warehouse_id' => $warehouseId,
                'type' => $type,
                'reason' => $reason,
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'conversion_factor' => $conversionFactor,
                'unit_price' => $unitPrice,
                'reference_type' => $referenceType,
                'reference_no' => $referenceNo,
                'distributor_id' => $distributorId,
                'user_id' => $userId,
                'moved_at' => $movedAt ?? Carbon::now(),
                'description' => $description,
            ]);

            $remaining = $quantity;

            foreach ($batches as $batch) {
                if (bccomp($remaining, '0', 4) <= 0) {
                    break;
                }

                $take = bccomp($remaining, (string) $batch->remaining, 4) <= 0
                    ? $remaining
                    : (string) $batch->remaining;

                StockMovementLine::create([
                    'stock_movement_id' => $movement->id,
                    'stock_batch_id' => $batch->id,
                    'quantity' => $take,
                    'unit_cost' => $batch->unit_cost,
                ]);

                $batch->update([
                    'remaining' => bcsub((string) $batch->remaining, $take, 4),
                ]);

                $remaining = bcsub($remaining, $take, 4);
            }

            $inventory->update([
                'quantity' => bcsub((string) $inventory->quantity, $quantity, 4),
            ]);

            return $movement->fresh(['product.baseUnit', 'fromWarehouse', 'unit', 'user', 'distributor.user', 'lines.stockBatch']);
        });
    }
}
