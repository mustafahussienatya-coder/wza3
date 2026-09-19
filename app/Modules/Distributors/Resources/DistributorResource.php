<?php

namespace App\Modules\Distributors\Resources;

use App\Modules\Areas\Resources\AreaResource;
use App\Modules\Distributors\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Distributor
 */
class DistributorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'username' => $this->user?->username,
            'phone' => $this->user?->phone,
            'is_active' => $this->user?->is_active,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_ar' => $this->status?->labelAr(),
            'vehicle_number' => $this->vehicle_number,
            'vehicle_type' => $this->vehicle_type?->value,
            'vehicle_type_label' => $this->vehicle_type?->label(),
            'vehicle_type_label_ar' => $this->vehicle_type?->labelAr(),
            'areas' => AreaResource::collection($this->whenLoaded('areas')),
            'national_id_documents' => [
                'front' => $this->documentUrl('front'),
                'back' => $this->documentUrl('back'),
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
