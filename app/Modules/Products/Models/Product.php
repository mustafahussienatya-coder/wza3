<?php

namespace App\Modules\Products\Models;

use App\Modules\Categories\Models\Category;
use App\Modules\Inventory\Models\Inventory;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Units\Models\Unit;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'category_id', 'base_unit_id', 'description', 'min_stock_level', 'status'])]
class Product extends Model
{
    use HasActivityLog;

    protected $casts = [
        'min_stock_level' => 'decimal:3',
        'status' => ProductStatus::class,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'product_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'product_id');
    }

    public function activeUnits(): HasMany
    {
        return $this->units()->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', ProductStatus::ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === ProductStatus::ACTIVE;
    }

    public static function generateCode(): string
    {
        return 'PRD-'.strtoupper(uniqid());
    }
}
