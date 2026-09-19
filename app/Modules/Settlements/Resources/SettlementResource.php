<?php

namespace App\Modules\Settlements\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'settlement_number' => $this->settlement_number,
            'distributor' => $this->whenLoaded('distributor', fn () => [
                'id' => $this->distributor?->id,
                'name' => $this->distributor?->user?->name,
            ]),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->labelAr(),
            'reference_no' => $this->reference_no,
            'settlement_date' => $this->settlement_date?->toISOString(),
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
