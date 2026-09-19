<?php

namespace App\Modules\Distributors\Resources;

use App\Modules\Distributors\Models\DistributorIssueCorrection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DistributorIssueCorrection
 */
class CorrectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'correction_no' => $this->correction_no,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_ar' => $this->status?->labelAr(),
            'notes' => $this->notes,
            'performed_by' => $this->whenLoaded('performedBy', fn () => [
                'id' => $this->performedBy->id,
                'name' => $this->performedBy->name,
            ]),
            'items' => CorrectionItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
