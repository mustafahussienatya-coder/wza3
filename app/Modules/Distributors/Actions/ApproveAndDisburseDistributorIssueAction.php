<?php

namespace App\Modules\Distributors\Actions;

use App\Modules\Distributors\Models\DistributorIssue;
use App\Modules\Notifications\Notifications\AppNotification;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApproveAndDisburseDistributorIssueAction
{
    public function __construct(
        private readonly ApproveDistributorIssueAction $approveAction,
        private readonly CompleteDistributorIssueAction $completeAction,
        private readonly NotificationService $notifications,
    ) {}

    public function execute(DistributorIssue $issue, int $userId, ?Carbon $movedAt = null): DistributorIssue
    {
        return DB::transaction(function () use ($issue, $userId, $movedAt) {
            $issue = $this->approveAction->execute($issue, $userId, notifyApproved: false);

            $issue = $this->completeAction->execute($issue, $userId, $movedAt);

            if ($issue->created_by !== $userId) {
                $this->notifications->sendToUser(
                    user: $issue->creator,
                    notification: new AppNotification(
                        code: 'custody.disbursed',
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
