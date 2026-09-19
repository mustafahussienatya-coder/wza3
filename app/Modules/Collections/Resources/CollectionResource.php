<?php

namespace App\Modules\Collections\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection_number' => $this->collection_number,
            'ownership' => $this->isCompany() ? 'company' : 'distributor',
            'distributor' => $this->whenLoaded('distributor', fn () => [
                'id' => $this->distributor?->id,
                'name' => $this->distributor?->user?->name,
            ]),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'ownership' => $this->customer->ownership,
            ]),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->labelAr(),
            'reference_no' => $this->reference_no,
            'collection_date' => $this->collection_date?->toISOString(),
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
