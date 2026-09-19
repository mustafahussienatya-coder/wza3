<?php

namespace App\Modules\Customers\Resources;

use App\Modules\Areas\Resources\AreaResource;
use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'address' => $this->address,
            'area' => new AreaResource($this->whenLoaded('area')),
            'area_id' => $this->area_id,
            'distributor_id' => $this->distributor_id,
            'distributor' => $this->whenLoaded('distributor', fn () => [
                'id' => $this->distributor->id,
                'name' => $this->distributor->user?->name,
            ]),
            'ownership' => $this->ownership,
            'ownership_label' => $this->ownership === 'distributor'
                ? __('customer_messages.distributor')
                : __('customer_messages.company'),
            'created_by' => $this->creator?->name,
            'created_by_id' => $this->created_by,
            'credit_limit' => $this->credit_limit,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_ar' => $this->status?->labelAr(),
            'notes' => $this->notes,
            'balance' => $this->balance,
            'outstanding_balance' => $this->outstanding_balance,
            'available_credit' => $this->available_credit,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
