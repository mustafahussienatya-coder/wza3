<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Collection;

class FifoService
{
    public function allocate(int $productId, int $warehouseId, string $baseQuantity): Collection
    {
        $batches = StockBatch::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $need = $baseQuantity;
        $lines = collect();

        foreach ($batches as $batch) {
            if (bccomp($need, '0', 4) <= 0) {
                break;
            }

            $take = bccomp($batch->remaining, $need, 4) >= 0 ? $need : $batch->remaining;

            $lines->push([
                'batch' => $batch,
                'quantity' => $take,
                'unit_cost' => $batch->unit_cost,
            ]);

            $need = bcsub($need, $take, 4);
        }

        if (bccomp($need, '0', 4) > 0) {
            $available = $batches->reduce(
                static fn (string $carry, StockBatch $batch): string => bcadd($carry, (string) $batch->remaining, 4),
                '0',
            );
            $product = Product::query()->with('baseUnit')->find($productId);

            throw new InsufficientStockException(
                product: $product?->name ?? null,
                available: $available,
                required: $baseQuantity,
                unit: $product?->baseUnit?->name ?? null,
            );
        }

        return $lines;
    }
}
