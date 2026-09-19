<?php

namespace App\Modules\Distributors\Actions;

use App\Modules\Distributors\Enums\DistributorIssueStatus;
use App\Modules\Distributors\Exceptions\InsufficientWarehouseStockException;
use App\Modules\Distributors\Exceptions\InvalidCustodyOperationException;
use App\Modules\Distributors\Exceptions\InvalidIssueStatusTransitionException;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Notifications\Notifications\AppNotification;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class ApproveDistributorIssueAction
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function execute(DistributorIssue $issue, int $userId, bool $notifyApproved = true): DistributorIssue
    {
        return DB::transaction(function () use ($issue, $userId, $notifyApproved) {
            $issue = DistributorIssue::query()
                ->whereKey($issue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $issue->isPendingApproval()) {
                throw new InvalidIssueStatusTransitionException;
            }

            if (! $issue->distributor->isActive() || ! $issue->warehouse->isActive()) {
                throw new InvalidCustodyOperationException;
            }

            $items = $issue->items()->get();

            $inventories = Inventory::query()
                ->where('warehouse_id', $issue->warehouse_id)
                ->whereIn('product_id', $items->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            foreach ($items as $item) {
                $inventory = $inventories->get($item->product_id);
                $available = $inventory === null ? '0' : (string) $inventory->quantity;

                if (bccomp($available, (string) $item->base_quantity, 4) < 0) {
                    throw new InsufficientWarehouseStockException;
                }
            }

            $issue->update([
                'status' => DistributorIssueStatus::APPROVED,
                'approved_by' => $userId,
            ]);

            $issue = $issue->fresh(['distributor.user', 'warehouse', 'creator', 'approver', 'completer', 'items.product.baseUnit', 'items.unit']);

            if ($notifyApproved && $issue->created_by !== $userId) {
                $this->notifications->sendToUser(
                    user: $issue->creator,
                    notification: new AppNotification(
                        code: 'custody.approved',
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
}
