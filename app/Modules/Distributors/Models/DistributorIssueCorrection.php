<?php

namespace App\Modules\Distributors\Models;

use App\Modules\Distributors\Enums\CorrectionStatus;
use App\Modules\Users\Models\User;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'correction_no',
    'issue_id',
    'status',
    'performed_by',
    'notes',
])]
class DistributorIssueCorrection extends Model
{
    use HasActivityLog;

    protected $casts = [
        'status' => CorrectionStatus::class,
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(DistributorIssue::class, 'issue_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DistributorIssueCorrectionItem::class, 'correction_id');
    }

    public static function generateCorrectionNo(): string
    {
        $latest = static::query()
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $next = 1;

        if ($latest !== null && preg_match('/^C-(\d+)$/', $latest->correction_no, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return 'C-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
