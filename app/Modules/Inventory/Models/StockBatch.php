<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Products\Models\Product;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'stock_movement_id',
    'product_id',
    'warehouse_id',
    'batch_no',
    'received_at',
    'quantity',
    'remaining',
    'unit_cost',
])]
class StockBatch extends Model
{
    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movementLines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    public static function generateBatchNo(): string
    {
        return 'B-'.strtoupper(uniqid());
    }
}
