<?php

namespace App\Modules\Distributors\Models;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_id',
    'product_id',
    'source_issue_id',
    'source_movement_id',
    'batch_no',
    'issued_at',
    'quantity',
    'remaining',
    'unit_price',
])]
class CustodyBatch extends Model
{
    public const UPDATED_AT = null;

    protected $casts = [
        'issued_at' => 'datetime',
        'quantity' => 'decimal:4',
        'remaining' => 'decimal:4',
        'unit_price' => 'decimal:2',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceIssue(): BelongsTo
    {
        return $this->belongsTo(DistributorIssue::class, 'source_issue_id');
    }

    public static function generateBatchNo(int $distributorId, int $productId): string
    {
        $existing = static::query()
            ->where('distributor_id', $distributorId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $sequence = 1;

        if ($existing !== null && preg_match('/-(\d+)$/', $existing->batch_no, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return 'CB-D'.$distributorId.'-P'.$productId.'-'.$sequence;
    }
}
