<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BulkStockInAction
{
    public function __construct(
        private readonly AddStockInAction $addStockInAction,
    ) {}

    /**
     * @param  array<int, array{
     *     product_id: int,
     *     warehouse_id: int,
     *     unit_id: int,
     *     quantity: string,
     *     unit_price: string,
     *     reason: string,
     *     moved_at?: string|null,
     *     description?: string|null,
     * }>  $rows
     * @return array<int, StockMovement>
     */
    public function execute(array $rows, int $userId): array
    {
        return DB::transaction(function () use ($rows, $userId) {
            return array_map(
                fn (array $row) => $this->addStockInAction->execute(
                    productId: (int) $row['product_id'],
                    warehouseId: (int) $row['warehouse_id'],
                    unitId: (int) $row['unit_id'],
                    quantity: (string) $row['quantity'],
                    unitPrice: (string) $row['unit_price'],
                    reason: StockMovementReason::from((string) $row['reason']),
                    movedAt: isset($row['moved_at']) && $row['moved_at'] !== '' ? Carbon::parse($row['moved_at']) : null,
                    description: $row['description'] ?? null,
                    userId: $userId,
                ),
                $rows
            );
        });
    }
}
