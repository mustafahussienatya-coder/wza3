<?php

namespace App\Modules\Invoices\Resources;

use App\Modules\Invoices\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Invoice $this */
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'ownership' => $this->ownership,
            'status' => $this->status->value,
            'status_label' => $this->status->labelAr(),
            'invoice_date' => $this->invoice_date?->toISOString(),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'ownership' => $this->customer->ownership,
            ]),
            'sales_distributor' => $this->whenLoaded('salesDistributor', fn () => [
                'id' => $this->salesDistributor?->id,
                'name' => $this->salesDistributor?->user?->name,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse?->id,
                'name' => $this->warehouse?->name,
            ]),
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'total_amount' => $this->total_amount,
            'paid_status' => $this->customer_paid_status ?? Invoice::getPaidStatus($this->customer_id),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'confirmed_by' => $this->whenLoaded('confirmer', fn () => $this->confirmer?->name),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancelled_by' => $this->whenLoaded('canceller', fn () => $this->canceller?->name),
            'cancellation_reason' => $this->cancellation_reason,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toISOString(),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
