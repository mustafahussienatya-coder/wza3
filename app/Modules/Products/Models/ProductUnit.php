<?php

namespace App\Modules\Products\Models;

use App\Modules\Units\Models\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'unit_id', 'conversion_factor', 'selling_price', 'cost_price', 'barcode', 'is_active'])]
class ProductUnit extends Model
{
    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function toBaseQuantity(float $quantity): float
    {
        return $quantity * (float) $this->conversion_factor;
    }

    public function isBaseUnit(): bool
    {
        return $this->unit_id === $this->product->base_unit_id;
    }
}
