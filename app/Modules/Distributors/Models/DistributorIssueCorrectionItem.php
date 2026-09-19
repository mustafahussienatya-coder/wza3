<?php

namespace App\Modules\Distributors\Models;

use App\Modules\Distributors\Enums\CorrectionType;
use App\Modules\Products\Models\Product;
use App\Modules\Units\Models\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'correction_id',
    'issue_item_id',
    'product_id',
    'correction_type',
    'original_unit_id',
    'corrected_unit_id',
    'original_quantity',
    'corrected_quantity',
    'original_base_quantity',
    'corrected_base_quantity',
    'original_conversion_factor',
    'corrected_conversion_factor',
    'original_unit_price',
    'corrected_unit_price',
    'total_before',
    'total_after',
    'value_difference',
])]
class DistributorIssueCorrectionItem extends Model
{
    protected $casts = [
        'correction_type' => CorrectionType::class,
        'original_quantity' => 'decimal:4',
        'corrected_quantity' => 'decimal:4',
        'original_base_quantity' => 'decimal:4',
        'corrected_base_quantity' => 'decimal:4',
        'original_conversion_factor' => 'decimal:4',
        'corrected_conversion_factor' => 'decimal:4',
        'original_unit_price' => 'decimal:2',
        'corrected_unit_price' => 'decimal:2',
        'total_before' => 'decimal:2',
        'total_after' => 'decimal:2',
        'value_difference' => 'decimal:2',
    ];

    public function correction(): BelongsTo
    {
        return $this->belongsTo(DistributorIssueCorrection::class, 'correction_id');
    }

    public function issueItem(): BelongsTo
    {
        return $this->belongsTo(DistributorIssueItem::class, 'issue_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function originalUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'original_unit_id');
    }

    public function correctedUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'corrected_unit_id');
    }
}
