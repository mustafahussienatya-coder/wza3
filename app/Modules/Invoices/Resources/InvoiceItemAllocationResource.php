<?php

namespace App\Modules\Invoices\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'custody_batch_id' => $this->custody_batch_id,
            'custody_batch_no' => $this->whenLoaded('custodyBatch', fn () => $this->custodyBatch?->batch_no),
            'stock_batch_id' => $this->stock_batch_id,
            'stock_batch_no' => $this->whenLoaded('stockBatch', fn () => $this->stockBatch?->batch_no),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
        ];
    }
}
