<?php

namespace App\Modules\Invoices\Models;

use App\Modules\Products\Models\Product;
use App\Modules\Units\Models\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_id',
    'product_id',
    'unit_id',
    'quantity',
    'base_quantity',
    'conversion_factor',
    'unit_price',
    'discount_amount',
    'line_total',
    'product_name',
    'unit_name',
])]
class InvoiceItem extends Model
{
    protected $casts = [
        'quantity' => 'decimal:4',
        'base_quantity' => 'decimal:4',
        'conversion_factor' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InvoiceItemAllocation::class);
    }
}
