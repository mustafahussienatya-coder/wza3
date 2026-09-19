<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\CannotCorrectMovementException;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementLine;
use App\Modules\Notifications\Notifications\AppNotification;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CorrectStockAction
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function execute(int $stockMovementId, int $userId): StockMovement
    {
        $original = StockMovement::findOrFail($stockMovementId);

        if (! in_array($original->type, [StockMovementType::STOCK_IN, StockMovementType::OPENING_BALANCE], true)) {
            throw new CannotCorrectMovementException;
        }

        return DB::transaction(function () use ($original, $userId) {
            $batch = StockBatch::query()
                ->where('stock_movement_id', $original->id)
                ->lockForUpdate()
                ->first();

            if ($batch === null || bccomp($batch->remaining, '0', 4) <= 0) {
                throw new CannotCorrectMovementException;
            }

            $inventory = Inventory::query()
                ->where('product_id', $original->product_id)
                ->where('warehouse_id', $original->to_warehouse_id)
                ->lockForUpdate()
                ->first();

            if ($inventory === null || bccomp($inventory->quantity, $batch->remaining, 4) < 0) {
                $product = Product::query()->with('baseUnit')->find($original->product_id);

                throw new InsufficientStockException(
                    product: $product?->name ?? null,
                    available: $inventory !== null ? bcadd($inventory->quantity, '0', 4) : '0',
                    required: $batch->remaining,
                    unit: $product?->baseUnit?->name ?? null,
                );
            }

            $reversed = $batch->remaining;

            $movement = StockMovement::create([
                'movement_no' => StockMovement::generateMovementNo(),
                'product_id' => $original->product_id,
                'from_warehouse_id' => $original->to_warehouse_id,
                'type' => StockMovementType::STOCK_OUT,
                'reason' => StockMovementReason::ENTRY_ERROR,
                'quantity' => $reversed,
                'unit_id' => $original->unit_id,
                'conversion_factor' => $original->conversion_factor,
                'unit_price' => null,
                'reference_type' => 'stock_movement',
                'reference_no' => $original->movement_no,
                'user_id' => $userId,
                'moved_at' => Carbon::now(),
            ]);

            StockMovementLine::create([
                'stock_movement_id' => $movement->id,
                'stock_batch_id' => $batch->id,
                'quantity' => $reversed,
                'unit_cost' => $batch->unit_cost,
            ]);

            $batch->update([
                'remaining' => bcsub($batch->remaining, $reversed, 4),
            ]);

            $inventory->update([
                'quantity' => bcsub($inventory->quantity, $reversed, 4),
            ]);

            $movement = $movement->fresh(['product.baseUnit', 'fromWarehouse', 'user', 'lines']);

            $this->notifications->sendToPermission(
                permission: 'inventory.correct',
                excludeUserIds: [$userId],
                notification: new AppNotification(
                    code: 'inventory.corrected',
                    data: [
                        'product_name' => $movement->product?->name,
                        'movement_no' => $movement->movement_no,
                        'original_movement_no' => $original->movement_no,
                    ],
                    link: '/warehouses/movements',
                ),
            );

            return $movement;
        });
    }
}
