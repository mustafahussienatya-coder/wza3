<?php

namespace App\Modules\Distributors\Actions;

use App\Modules\Distributors\Enums\DistributorIssueStatus;
use App\Modules\Distributors\Exceptions\InvalidIssueStatusTransitionException;
use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Notifications\Notifications\AppNotification;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class SubmitDistributorIssueAction
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function execute(DistributorIssue $issue, int $userId): DistributorIssue
    {
        return DB::transaction(function () use ($issue, $userId) {
            $issue = DistributorIssue::query()
                ->whereKey($issue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $issue->isDraft()) {
                throw new InvalidIssueStatusTransitionException;
            }

            $issue->update([
                'status' => DistributorIssueStatus::PENDING_APPROVAL,
            ]);

            $issue = $issue->fresh([
                'distributor.user',
                'warehouse',
                'creator',
                'items.product.baseUnit',
                'items.unit',
            ]);

            $this->notifications->sendToPermission(
                permission: 'custody.approve_disburse',
                excludeUserIds: [$userId],
                notification: new AppNotification(
                    code: 'custody.approval_requested',
                    data: [
                        'issue_number' => $issue->issue_number,
                        'distributor_name' => $issue->distributor?->user?->name,
                    ],
                    link: '/custody/disburse',
                ),
            );

            return $issue;
        });
    }
}
