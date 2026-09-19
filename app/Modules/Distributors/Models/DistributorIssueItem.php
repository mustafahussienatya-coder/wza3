<?php

namespace App\Modules\Distributors\Models;

use App\Modules\Distributors\Exceptions\InvalidCustodyOperationException;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductUnit;
use App\Modules\Units\Models\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'distributor_issue_id',
    'product_id',
    'unit_id',
    'quantity',
    'base_quantity',
    'conversion_factor',
    'unit_price',
])]
class DistributorIssueItem extends Model
{
    public $timestamps = false;

    protected $casts = [
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'unit_price' => 'decimal:2',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(DistributorIssue::class, 'distributor_issue_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public static function normalizeRows(array $items): array
    {
        $rows = [];

        foreach ($items as $item) {
            $product = Product::findOrFail($item['product_id']);

            if (! $product->isActive()) {
                throw new InvalidCustodyOperationException;
            }

            $productUnit = ProductUnit::query()
                ->where('product_id', $product->id)
                ->where('unit_id', $item['unit_id'])
                ->where('is_active', true)
                ->first();

            if ($productUnit === null) {
                throw new InvalidCustodyOperationException;
            }

            $quantity = (string) $item['quantity'];
            $factor = (string) $productUnit->conversion_factor;

            $rows[] = [
                'product_id' => $product->id,
                'unit_id' => $item['unit_id'],
                'quantity' => bcadd($quantity, '0', 4),
                'base_quantity' => bcmul($quantity, $factor, 4),
                'conversion_factor' => $factor,
                'unit_price' => bcadd((string) ($item['unit_price'] ?? $productUnit->selling_price ?? '0'), '0', 2),
            ];
        }

        return $rows;
    }
}
