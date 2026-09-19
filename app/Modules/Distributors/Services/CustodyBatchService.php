<?php

namespace App\Modules\Distributors\Services;

use App\Modules\Distributors\Exceptions\InsufficientDistributorStockException;
use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Products\Models\Product;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class CustodyBatchService
{
    public function issue(
        int $distributorId,
        int $productId,
        ?int $issueId,
        ?int $sourceMovementId,
        string $baseQuantity,
        string $unitPrice,
        DateTimeInterface|string $issuedAt,
    ): CustodyBatch {
        return CustodyBatch::create([
            'distributor_id' => $distributorId,
            'product_id' => $productId,
            'source_issue_id' => $issueId,
            'source_movement_id' => $sourceMovementId,
            'batch_no' => CustodyBatch::generateBatchNo($distributorId, $productId),
            'issued_at' => $issuedAt instanceof DateTimeInterface
                ? Carbon::instance($issuedAt)
                : Carbon::parse($issuedAt),
            'quantity' => $baseQuantity,
            'remaining' => $baseQuantity,
            'unit_price' => $unitPrice,
        ]);
    }

    public function consume(int $distributorId, int $productId, string $baseQuantity): void
    {
        $need = $baseQuantity;

        $batches = CustodyBatch::query()
            ->where('distributor_id', $distributorId)
            ->where('product_id', $productId)
            ->where('remaining', '>', 0)
            ->orderBy('issued_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = $batches->reduce(
            static fn (string $carry, CustodyBatch $batch): string => bcadd($carry, (string) $batch->remaining, 4),
            '0',
        );

        foreach ($batches as $batch) {
            if (bccomp($need, '0', 4) <= 0) {
                break;
            }

            $take = bccomp((string) $batch->remaining, $need, 4) >= 0
                ? $need
                : (string) $batch->remaining;

            $batch->update([
                'remaining' => bcsub((string) $batch->remaining, $take, 4),
            ]);

            $need = bcsub($need, $take, 4);
        }

        if (bccomp($need, '0', 4) > 0) {
            $product = Product::query()->with('baseUnit')->find($productId);

            throw new InsufficientDistributorStockException(
                product: $product?->name ?? null,
                available: $available,
                required: $baseQuantity,
                unit: $product?->baseUnit?->name ?? null,
            );
        }
    }
}
