<?php

namespace App\Modules\Distributors\Models;

use App\Modules\Distributors\Enums\DistributorIssueStatus;
use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'issue_number',
    'distributor_id',
    'warehouse_id',
    'status',
    'created_by',
    'approved_by',
    'completed_by',
    'notes',
])]
class DistributorIssue extends Model
{
    use HasActivityLog;

    public const MOVEMENT_REFERENCE_TYPE = 'distributor_issue';

    protected $casts = [
        'status' => DistributorIssueStatus::class,
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DistributorIssueItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(DistributorIssueCorrection::class, 'issue_id');
    }

    public function isDraft(): bool
    {
        return $this->status === DistributorIssueStatus::DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === DistributorIssueStatus::PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === DistributorIssueStatus::APPROVED;
    }

    public function isCompleted(): bool
    {
        return $this->status === DistributorIssueStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === DistributorIssueStatus::CANCELLED;
    }

    public function isReturned(): bool
    {
        return $this->status === DistributorIssueStatus::RETURNED;
    }

    public static function generateIssueNumber(): string
    {
        $latest = static::query()
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $next = 1;

        if ($latest !== null && preg_match('/^DI-(\d+)$/', $latest->issue_number, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return 'DI-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
