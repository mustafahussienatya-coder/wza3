<?php

namespace App\Modules\Distributors\Actions;

use App\Modules\Distributors\Enums\CustodyMovementType;
use App\Modules\Distributors\Enums\DistributorIssueStatus;
use App\Modules\Distributors\Exceptions\InsufficientDistributorStockException;
use App\Modules\Distributors\Exceptions\InvalidCustodyOperationException;
use App\Modules\Distributors\Exceptions\InvalidIssueStatusTransitionException;
use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\DistributorInventory;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Distributors\Models\DistributorIssueItem;
use App\Modules\Distributors\Services\CustodyBatchService;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementLine;
use App\Modules\Notifications\Notifications\AppNotification;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReturnDistributorIssueAction
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CustodyBatchService $custodyBatches,
    ) {}

    public function execute(DistributorIssue $issue, int $userId): DistributorIssue
    {
        return DB::transaction(function () use ($issue, $userId) {
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
                ->sortBy('product_id')
                ->values();

            $custodySnapshots = DistributorInventory::query()
                ->where('distributor_id', $issue->distributor_id)
                ->whereIn('product_id', $items->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($items as $item) {
                $snapshot = $custodySnapshots->get($item->product_id);

                if ($snapshot === null || bccomp((string) $snapshot->quantity, (string) $item->base_quantity, 4) < 0) {
                    throw new InsufficientDistributorStockException(
                        product: $item->product?->name ?? null,
                        available: $snapshot !== null ? (string) $snapshot->quantity : '0',
                        required: (string) $item->base_quantity,
                        unit: $item->product?->baseUnit?->name ?? null,
                    );
                }
            }

            $movingAt = Carbon::now();

            foreach ($items as $item) {
                $this->restoreConsumedStock($issue, $item);

                $snapshot = $custodySnapshots->get($item->product_id);

                $snapshot->update([
                    'quantity' => bcsub((string) $snapshot->quantity, (string) $item->base_quantity, 4),
                ]);

                StockMovement::create([
                    'movement_no' => StockMovement::generateMovementNo(),
                    'product_id' => $item->product_id,
                    'to_warehouse_id' => $issue->warehouse_id,
                    'type' => StockMovementType::CUSTODY_RETURN,
                    'reason' => StockMovementReason::CUSTODY_RETURN,
                    'quantity' => (string) $item->base_quantity,
                    'unit_id' => $item->unit_id,
                    'conversion_factor' => (string) $item->conversion_factor,
                    'unit_price' => (string) $item->unit_price,
                    'reference_type' => DistributorIssue::MOVEMENT_REFERENCE_TYPE,
                    'reference_no' => $issue->issue_number,
                    'distributor_id' => $issue->distributor_id,
                    'user_id' => $userId,
                    'moved_at' => $movingAt,
                ]);

                CustodyMovement::create([
                    'distributor_id' => $issue->distributor_id,
                    'product_id' => $item->product_id,
                    'movement_type' => CustodyMovementType::RETURN_TO_WAREHOUSE,
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

                $this->custodyBatches->consume(
                    distributorId: $issue->distributor_id,
                    productId: $item->product_id,
                    baseQuantity: (string) $item->base_quantity,
                );
            }

            $issue->update([
                'status' => DistributorIssueStatus::RETURNED,
                'completed_by' => $userId,
            ]);

            $issue = $issue->fresh(['distributor.user', 'warehouse', 'creator', 'approver', 'completer', 'items.product.baseUnit', 'items.unit']);

            if ($issue->created_by !== $userId) {
                $this->notifications->sendToUser(
                    user: $issue->creator,
                    notification: new AppNotification(
                        code: 'custody.returned',
                        data: [
                            'issue_number' => $issue->issue_number,
                            'distributor_name' => $issue->distributor?->user?->name,
                        ],
                        link: '/custody/follow',
                    ),
                );
            }

            return $issue;
        });
    }

    private function restoreConsumedStock(DistributorIssue $issue, DistributorIssueItem $item): void
    {
        $issuedMovementIds = StockMovement::query()
            ->where('reference_type', DistributorIssue::MOVEMENT_REFERENCE_TYPE)
            ->where('reference_no', $issue->issue_number)
            ->where('type', StockMovementType::DISTRIBUTOR_ISSUE)
            ->where('product_id', $item->product_id)
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

        foreach ($lines as $line) {
            $batch = $batches->get($line->stock_batch_id);

            if ($batch === null) {
                continue;
            }

            $batch->update([
                'remaining' => bcadd((string) $batch->remaining, (string) $line->quantity, 4),
            ]);
        }

        $inventory = Inventory::query()
            ->where('product_id', $item->product_id)
            ->where('warehouse_id', $issue->warehouse_id)
            ->lockForUpdate()
            ->first();

        if ($inventory === null) {
            $inventory = Inventory::create([
                'product_id' => $item->product_id,
                'warehouse_id' => $issue->warehouse_id,
                'quantity' => '0',
            ]);
        }

        $inventory->update([
            'quantity' => bcadd((string) $inventory->quantity, (string) $item->base_quantity, 4),
        ]);
    }
}
