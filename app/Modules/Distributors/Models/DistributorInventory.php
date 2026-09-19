<?php

namespace App\Modules\Distributors\Models;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['distributor_id', 'product_id', 'quantity'])]
class DistributorInventory extends Model
{
    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
