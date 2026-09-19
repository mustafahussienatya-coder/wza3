<?php

namespace App\Modules\Invoices\Models;

use App\Modules\Distributors\Models\CustodyBatch;
use App\Modules\Inventory\Models\StockBatch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_item_id',
    'custody_batch_id',
    'stock_batch_id',
    'quantity',
    'unit_price',
])]
class InvoiceItemAllocation extends Model
{
    public const UPDATED_AT = null;

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
    ];

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function custodyBatch(): BelongsTo
    {
        return $this->belongsTo(CustodyBatch::class);
    }

    public function stockBatch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class);
    }
}
