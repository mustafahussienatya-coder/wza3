<?php

namespace App\Modules\Distributors\Actions;

use App\Modules\Distributors\Enums\CustodyMovementType;
use App\Modules\Distributors\Enums\DistributorIssueStatus;
use App\Modules\Distributors\Exceptions\InsufficientWarehouseStockException;
use App\Modules\Distributors\Exceptions\InvalidCustodyOperationException;
use App\Modules\Distributors\Exceptions\InvalidIssueStatusTransitionException;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Distributors\Services\CustodyBatchService;
use App\Modules\Inventory\Actions\CreateStockOutAction;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CompleteDistributorIssueAction
{
    public function __construct(
        private readonly CreateStockOutAction $createStockOutAction,
        private readonly CustodyBatchService $custodyBatches,
    ) {}

    public function execute(DistributorIssue $issue, int $userId, ?Carbon $movedAt = null): DistributorIssue
    {
        return DB::transaction(function () use ($issue, $userId, $movedAt) {
            $issue = DistributorIssue::query()
                ->whereKey($issue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $issue->isApproved()) {
                throw new InvalidIssueStatusTransitionException;
            }

            if (! $issue->distributor->isActive() || ! $issue->warehouse->isActive()) {
                throw new InvalidCustodyOperationException;
            }

            $items = $issue->items()
                ->with(['product', 'unit'])
                ->get()
                ->sortBy('product_id')
                ->values();

            $custodySnapshots = DistributorInventory::query()
                ->where('distributor_id', $issue->distributor_id)
                ->whereIn('product_id', $items->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            $movingAt = ($movedAt ?? Carbon::now())->toDateTimeString();

            foreach ($items as $item) {
                try {
                    $this->createStockOutAction->execute(
                        productId: $item->product_id,
                        warehouseId: $issue->warehouse_id,
                        quantity: (string) $item->base_quantity,
                        reason: StockMovementReason::DISTRIBUTOR_ISSUE,
                        type: StockMovementType::DISTRIBUTOR_ISSUE,
                        unitId: $item->unit_id,
                        conversionFactor: (string) $item->conversion_factor,
                        unitPrice: (string) $item->unit_price,
                        referenceType: DistributorIssue::MOVEMENT_REFERENCE_TYPE,
                        referenceNo: $issue->issue_number,
                        distributorId: $issue->distributor_id,
                        userId: $userId,
                        movedAt: Carbon::parse($movingAt),
                    );
                } catch (InsufficientStockException) {
                    throw new InsufficientWarehouseStockException;
                }

                $snapshot = $custodySnapshots->get($item->product_id);

                if ($snapshot === null) {
                    DistributorInventory::create([
                        'distributor_id' => $issue->distributor_id,
                        'product_id' => $item->product_id,
                        'quantity' => (string) $item->base_quantity,
                    ]);
                } else {
                    $snapshot->update([
                        'quantity' => bcadd((string) $snapshot->quantity, (string) $item->base_quantity, 4),
                    ]);
                }

                $movement = CustodyMovement::create([
                    'distributor_id' => $issue->distributor_id,
                    'product_id' => $item->product_id,
                    'movement_type' => CustodyMovementType::ISSUE,
                    'quantity' => (string) $item->quantity,
                    'unit_id' => $item->unit_id,
                    'base_quantity' => (string) $item->base_quantity,
                    'conversion_factor' => (string) $item->conversion_factor,
                    'selling_price' => (string) $item->unit_price,
                    'reference_type' => DistributorIssue::MOVEMENT_REFERENCE_TYPE,
                    'reference_id' => $issue->id,
                    'performed_by' => $userId,
                    'created_at' => $movingAt,
                ]);

                $this->custodyBatches->issue(
                    distributorId: $issue->distributor_id,
                    productId: $item->product_id,
                    issueId: $issue->id,
                    sourceMovementId: $movement->id,
                    baseQuantity: (string) $item->base_quantity,
                    unitPrice: (string) $item->unit_price,
                    issuedAt: $movingAt,
                );
            }

            $issue->update([
                'status' => DistributorIssueStatus::COMPLETED,
                'completed_by' => $userId,
            ]);

            return $issue->fresh(['distributor.user', 'warehouse', 'creator', 'approver', 'completer', 'items.product.baseUnit', 'items.unit']);
        });
    }
}
