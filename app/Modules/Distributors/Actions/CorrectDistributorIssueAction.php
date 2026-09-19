<?php

namespace App\Modules\Distributors\Actions;

use App\Modules\Distributors\Enums\CorrectionType;
use App\Modules\Distributors\Enums\CustodyMovementType;
use App\Modules\Distributors\Exceptions\EmptyCorrectionException;
use App\Modules\Distributors\Exceptions\InsufficientDistributorStockException;
use App\Modules\Distributors\Exceptions\InsufficientWarehouseStockException;
use App\Modules\Distributors\Exceptions\InvalidCustodyOperationException;
use App\Modules\Distributors\Exceptions\InvalidIssueStatusTransitionException;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Distributors\Models\DistributorIssueCorrection;
use App\Modules\Distributors\Models\DistributorIssueCorrectionItem;
use App\Modules\Distributors\Models\DistributorIssueItem;
use App\Modules\Distributors\Services\CustodyBatchService;
use App\Modules\Inventory\Actions\CreateStockOutAction;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementLine;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CorrectDistributorIssueAction
{
    public function __construct(
        private readonly CreateStockOutAction $createStockOutAction,
        private readonly CustodyBatchService $custodyBatches,
    ) {}

    public function execute(DistributorIssue $issue, array $data, int $userId): DistributorIssue
    {
        return DB::transaction(function () use ($issue, $data, $userId) {
            $issue = DistributorIssue::query()
                ->whereKey($issue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $issue->isCompleted()) {
                throw new InvalidIssueStatusTransitionException;
            }

            if (! $issue->distributor->isActive() || ! $issue->warehouse->isActive()) {
                throw new InvalidCustodyOperationException;
            }

            $items = $issue->items()
                ->with(['product', 'unit'])
                ->get()
                ->keyBy('id');

            $rows = collect($data['items'])->keyBy('id');

            $inventorySnapshots = DistributorInventory::query()
                ->where('distributor_id', $issue->distributor_id)
                ->whereIn('product_id', $items->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $correction = DistributorIssueCorrection::create([
                'correction_no' => DistributorIssueCorrection::generateCorrectionNo(),
                'issue_id' => $issue->id,
                'performed_by' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            $movedAt = Carbon::now();
            $snapshotChanges = [];

            foreach ($rows as $itemId => $row) {
                $item = $items->get((int) $itemId)
                    ?? throw new InvalidCustodyOperationException;

                $this->correctItem(
                    issue: $issue,
                    item: $item,
                    row: $row,
                    correction: $correction,
                    inventorySnapshots: $inventorySnapshots,
                    snapshotChanges: $snapshotChanges,
                    userId: $userId,
                    movedAt: $movedAt,
                );
            }

            foreach ($snapshotChanges as $productId => $change) {
                $snapshot = $inventorySnapshots->get((int) $productId);

                if ($snapshot === null || bccomp($change, '0', 4) === 0) {
                    continue;
                }

                $snapshot->update([
                    'quantity' => bcadd((string) $snapshot->quantity, $change, 4),
                ]);
            }

            return $issue->fresh([
                'distributor.user',
                'warehouse',
                'creator',
                'approver',
                'completer',
                'items.product.baseUnit',
                'items.unit',
                'corrections.performedBy',
                'corrections.items.product',
                'corrections.items.originalUnit',
                'corrections.items.correctedUnit',
            ]);
        });
    }

    private function correctItem(
        DistributorIssue $issue,
        DistributorIssueItem $item,
        array $row,
        DistributorIssueCorrection $correction,
        Collection $inventorySnapshots,
        array &$snapshotChanges,
        int $userId,
        Carbon $movedAt,
    ): void {
        $productId = $item->product_id;

        $originalQuantity = (string) $item->quantity;
        $originalUnitId = $item->unit_id;
        $originalFactor = (string) $item->conversion_factor;
        $originalPrice = (string) $item->unit_price;
        $originalBase = (string) $item->base_quantity;

        $correctedQuantity = isset($row['quantity'])
            ? bcadd((string) $row['quantity'], '0', 4)
            : $originalQuantity;
        $correctedUnitId = isset($row['unit_id'])
            ? (int) $row['unit_id']
            : $originalUnitId;
        $correctedPrice = isset($row['unit_price'])
            ? bcadd((string) $row['unit_price'], '0', 2)
            : $originalPrice;

        $correctedFactor = $originalFactor;
        if ($correctedUnitId !== $originalUnitId) {
            $productUnit = ProductUnit::query()
                ->where('product_id', $productId)
                ->where('unit_id', $correctedUnitId)
                ->where('is_active', true)
                ->first();

            if ($productUnit === null) {
                throw new InvalidCustodyOperationException;
            }

            $correctedFactor = (string) $productUnit->conversion_factor;
        }

        $correctedBase = bcmul($correctedQuantity, $correctedFactor, 4);

        $quantityChanged = bccomp($correctedBase, $originalBase, 4) !== 0;
        $unitChanged = $correctedUnitId !== $originalUnitId;
        $priceChanged = bccomp($correctedPrice, $originalPrice, 2) !== 0;

        if (! $quantityChanged && ! $unitChanged && ! $priceChanged) {
            throw new EmptyCorrectionException;
        }

        $correctionType = $this->resolveType($quantityChanged, $unitChanged, $priceChanged);

        $totalBefore = bcmul($originalQuantity, $originalPrice, 2);
        $totalAfter = bcmul($correctedQuantity, $correctedPrice, 2);
        $valueDifference = bcsub($totalAfter, $totalBefore, 2);

        DistributorIssueCorrectionItem::create([
            'correction_id' => $correction->id,
            'issue_item_id' => $item->id,
            'product_id' => $productId,
            'correction_type' => $correctionType,
            'original_unit_id' => $originalUnitId,
            'corrected_unit_id' => $correctedUnitId,
            'original_quantity' => $originalQuantity,
            'corrected_quantity' => $correctedQuantity,
            'original_base_quantity' => $originalBase,
            'corrected_base_quantity' => $correctedBase,
            'original_conversion_factor' => $originalFactor,
            'corrected_conversion_factor' => $correctedFactor,
            'original_unit_price' => $originalPrice,
            'corrected_unit_price' => $correctedPrice,
            'total_before' => $totalBefore,
            'total_after' => $totalAfter,
            'value_difference' => $valueDifference,
        ]);

        $snapshot = $inventorySnapshots->get($productId);
        $currentDistributorBase = $snapshot !== null
            ? bcadd((string) $snapshot->quantity, ($snapshotChanges[$productId] ?? '0'), 4)
            : '0';

        $baseDelta = '0';

        if ($quantityChanged) {
            $difference = bcsub($correctedBase, $originalBase, 4);

            if (bccomp($difference, '0', 4) > 0) {
                $this->issueMore(
                    issue: $issue,
                    productId: $productId,
                    difference: $difference,
                    correctedUnitId: $correctedUnitId,
                    correctedFactor: $correctedFactor,
                    correctedPrice: $correctedPrice,
                    userId: $userId,
                    movedAt: $movedAt,
                );
            } else {
                $returnBase = bcsub('0', $difference, 4);

                if (bccomp($currentDistributorBase, $returnBase, 4) < 0) {
                    $product = Product::query()->with('baseUnit')->find($productId);

                    throw new InsufficientDistributorStockException(
                        product: $product?->name ?? null,
                        available: $currentDistributorBase,
                        required: $returnBase,
                        unit: $product?->baseUnit?->name ?? null,
                    );
                }

                $this->returnToWarehouse(
                    issue: $issue,
                    productId: $productId,
                    returnBase: $returnBase,
                    unitId: $originalUnitId,
                    factor: $originalFactor,
                    price: $originalPrice,
                    userId: $userId,
                    movedAt: $movedAt,
                );

                $difference = bcsub('0', $returnBase, 4);
            }

            $baseDelta = $difference;
            $snapshotChanges[$productId] = bcadd($snapshotChanges[$productId] ?? '0', $baseDelta, 4);
        }

        if ($priceChanged) {
            $remaining = bcadd($currentDistributorBase, $baseDelta, 4);

            if (bccomp($remaining, '0', 4) > 0) {
                $this->returnToWarehouse(
                    issue: $issue,
                    productId: $productId,
                    returnBase: $remaining,
                    unitId: $originalUnitId,
                    factor: $originalFactor,
                    price: $originalPrice,
                    userId: $userId,
                    movedAt: $movedAt,
                );

                $this->issueMore(
                    issue: $issue,
                    productId: $productId,
                    difference: $remaining,
                    correctedUnitId: $correctedUnitId,
                    correctedFactor: $correctedFactor,
                    correctedPrice: $correctedPrice,
                    userId: $userId,
                    movedAt: $movedAt,
                );
            }
        }
    }

    private function issueMore(
        DistributorIssue $issue,
        int $productId,
        string $difference,
        int $correctedUnitId,
        string $correctedFactor,
        string $correctedPrice,
        int $userId,
        Carbon $movedAt,
    ): void {
        try {
            $this->createStockOutAction->execute(
                productId: $productId,
                warehouseId: $issue->warehouse_id,
                quantity: $difference,
                reason: StockMovementReason::DISTRIBUTOR_ISSUE,
                type: StockMovementType::DISTRIBUTOR_ISSUE,
                unitId: $correctedUnitId,
                conversionFactor: $correctedFactor,
                unitPrice: $correctedPrice,
                referenceType: DistributorIssue::MOVEMENT_REFERENCE_TYPE,
                referenceNo: $issue->issue_number,
                distributorId: $issue->distributor_id,
                userId: $userId,
                movedAt: $movedAt,
            );
        } catch (InsufficientStockException) {
            throw new InsufficientWarehouseStockException;
        }

        $movement = CustodyMovement::create([
            'distributor_id' => $issue->distributor_id,
            'product_id' => $productId,
            'movement_type' => CustodyMovementType::ISSUE,
            'quantity' => bcdiv($difference, $correctedFactor, 4),
            'unit_id' => $correctedUnitId,
            'base_quantity' => $difference,
            'conversion_factor' => $correctedFactor,
            'selling_price' => $correctedPrice,
            'reference_type' => DistributorIssue::MOVEMENT_REFERENCE_TYPE,
            'reference_id' => $issue->id,
            'performed_by' => $userId,
            'created_at' => $movedAt,
        ]);

        $this->custodyBatches->issue(
            distributorId: $issue->distributor_id,
            productId: $productId,
            issueId: $issue->id,
            sourceMovementId: $movement->id,
            baseQuantity: $difference,
            unitPrice: $correctedPrice,
            issuedAt: $movedAt,
        );
    }

    private function returnToWarehouse(
        DistributorIssue $issue,
        int $productId,
        string $returnBase,
        int $unitId,
        string $factor,
        string $price,
        int $userId,
        Carbon $movedAt,
    ): void {
        $returnMovement = StockMovement::create([
            'movement_no' => StockMovement::generateMovementNo(),
            'product_id' => $productId,
            'to_warehouse_id' => $issue->warehouse_id,
            'type' => StockMovementType::CUSTODY_RETURN,
            'reason' => StockMovementReason::CUSTODY_RETURN,
            'quantity' => $returnBase,
            'unit_id' => $unitId,
            'conversion_factor' => $factor,
            'unit_price' => $price,
            'reference_type' => DistributorIssue::MOVEMENT_REFERENCE_TYPE,
            'reference_no' => $issue->issue_number,
            'distributor_id' => $issue->distributor_id,
            'user_id' => $userId,
            'moved_at' => $movedAt,
        ]);

        $this->restoreToOriginalBatches(
            issue: $issue,
            productId: $productId,
            returnBase: $returnBase,
            returnMovement: $returnMovement,
        );

        CustodyMovement::create([
            'distributor_id' => $issue->distributor_id,
            'product_id' => $productId,
            'movement_type' => CustodyMovementType::RETURN_TO_WAREHOUSE,
            'quantity' => bcdiv($returnBase, $factor, 4),
            'unit_id' => $unitId,
            'base_quantity' => $returnBase,
            'conversion_factor' => $factor,
            'selling_price' => $price,
            'reference_type' => DistributorIssue::MOVEMENT_REFERENCE_TYPE,
            'reference_id' => $issue->id,
            'performed_by' => $userId,
            'created_at' => $movedAt,
        ]);

        $this->custodyBatches->consume(
            distributorId: $issue->distributor_id,
            productId: $productId,
            baseQuantity: $returnBase,
        );
    }

    private function restoreToOriginalBatches(
        DistributorIssue $issue,
        int $productId,
        string $returnBase,
        StockMovement $returnMovement,
    ): void {
        $issuedMovementIds = StockMovement::query()
            ->where('reference_type', DistributorIssue::MOVEMENT_REFERENCE_TYPE)
            ->where('reference_no', $issue->issue_number)
            ->where('type', StockMovementType::DISTRIBUTOR_ISSUE)
            ->where('product_id', $productId)
            ->pluck('id');

        $lines = StockMovementLine::query()
            ->whereIn('stock_movement_id', $issuedMovementIds)
            ->lockForUpdate()
            ->get();

        $batches = StockBatch::query()
            ->whereIn('id', $lines->pluck('stock_batch_id')->unique())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $remainingToRestore = $returnBase;

        foreach ($lines as $line) {
            if (bccomp($remainingToRestore, '0', 4) <= 0) {
                break;
            }

            $batch = $batches->get($line->stock_batch_id);

            if ($batch === null) {
                continue;
            }

            $take = bccomp($remainingToRestore, (string) $line->quantity, 4) <= 0
                ? $remainingToRestore
                : (string) $line->quantity;

            $batch->update([
                'remaining' => bcadd((string) $batch->remaining, $take, 4),
            ]);

            StockMovementLine::create([
                'stock_movement_id' => $returnMovement->id,
                'stock_batch_id' => $batch->id,
                'quantity' => $take,
                'unit_cost' => $batch->unit_cost,
            ]);

            $remainingToRestore = bcsub($remainingToRestore, $take, 4);
        }

        $inventory = Inventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $issue->warehouse_id)
            ->lockForUpdate()
            ->first();

        if ($inventory === null) {
            $inventory = Inventory::create([
                'product_id' => $productId,
                'warehouse_id' => $issue->warehouse_id,
                'quantity' => '0',
            ]);
        }

        $inventory->update([
            'quantity' => bcadd((string) $inventory->quantity, $returnBase, 4),
        ]);
    }

    private function resolveType(bool $quantityChanged, bool $unitChanged, bool $priceChanged): CorrectionType
    {
        $changed = array_filter([
            $quantityChanged,
            $unitChanged,
            $priceChanged,
        ]);

        return count($changed) > 1
            ? CorrectionType::MIXED
            : match (true) {
                $quantityChanged => CorrectionType::QUANTITY,
                $unitChanged => CorrectionType::UNIT,
                $priceChanged => CorrectionType::PRICE,
                default => CorrectionType::MIXED,
            };
    }
}
