<?php

namespace App\Modules\Invoices\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'unit_id' => $this->unit_id,
            'unit_name' => $this->unit_name,
            'quantity' => $this->quantity,
            'base_quantity' => $this->base_quantity,
            'conversion_factor' => $this->conversion_factor,
            'unit_price' => $this->unit_price,
            'discount_amount' => $this->discount_amount,
            'line_total' => $this->line_total,
            'allocations' => InvoiceItemAllocationResource::collection($this->whenLoaded('allocations')),
        ];
    }
}
