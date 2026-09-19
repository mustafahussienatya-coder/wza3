<?php

namespace App\Modules\Invoices\Services;

use App\Modules\Distributors\Exceptions\InsufficientDistributorStockException;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Products\Models\Product;
use Illuminate\Support\Collection;

class CustodyFifoService
{
    /**
     * تخصيص كمية بالدفعات (FIFO) من عهدة موزع.
     *
     * @return Collection<int, array{batch: CustodyBatch, quantity: string}>
     */
    public function allocate(int $distributorId, int $productId, string $baseQuantity): Collection
    {
        $batches = CustodyBatch::query()
            ->where('distributor_id', $distributorId)
            ->where('product_id', $productId)
            ->where('remaining', '>', 0)
            ->orderBy('issued_at')
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
            ]);

            $need = bcsub($need, $take, 4);
        }

        if (bccomp($need, '0', 4) > 0) {
            $available = $batches->reduce(
                static fn (string $carry, CustodyBatch $batch): string => bcadd($carry, (string) $batch->remaining, 4),
                '0',
            );
            $product = Product::query()->with('baseUnit')->find($productId);

            throw new InsufficientDistributorStockException(
                product: $product?->name ?? null,
                available: $available,
                required: $baseQuantity,
                unit: $product?->baseUnit?->name ?? null,
            );
        }

        return $lines;
    }
}
