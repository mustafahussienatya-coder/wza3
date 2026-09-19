<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Distributors\Models\Distributor;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Products\Models\Product;
use App\Modules\Units\Models\Unit;
use App\Modules\Users\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'movement_no',
    'product_id',
    'from_warehouse_id',
    'to_warehouse_id',
    'type',
    'reason',
    'quantity',
    'unit_id',
    'conversion_factor',
    'unit_price',
    'distributor_id',
    'reference_type',
    'reference_no',
    'user_id',
    'moved_at',
    'description',
])]
class StockMovement extends Model
{
    use HasActivityLog;

    protected $casts = [
        'type' => StockMovementType::class,
        'reason' => StockMovementReason::class,
        'moved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    public static function generateMovementNo(): string
    {
        return 'MV-'.strtoupper(uniqid());
    }
}
