<?php

namespace App\Modules\Distributors\Models;

use App\Modules\Distributors\Enums\CustodyMovementType;
use App\Modules\Products\Models\Product;
use App\Modules\Units\Models\Unit;
use App\Modules\Users\Models\User;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_id',
    'product_id',
    'movement_type',
    'quantity',
    'unit_id',
    'base_quantity',
    'conversion_factor',
    'selling_price',
    'reference_type',
    'reference_id',
    'performed_by',
    'reason',
])]
class CustodyMovement extends Model
{
    use HasActivityLog;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $casts = [
        'movement_type' => CustodyMovementType::class,
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'selling_price' => 'decimal:4',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function referenceIssue(): BelongsTo
    {
        return $this->belongsTo(DistributorIssue::class, 'reference_id', 'id');
    }
}
