<?php

namespace App\Modules\Distributors\Resources;

use App\Modules\Distributors\Models\DistributorIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DistributorIssue
 */
class DistributorIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'issue_number' => $this->issue_number,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'status_label_ar' => $this->status?->labelAr(),
            'distributor' => $this->whenLoaded('distributor', fn () => [
                'id' => $this->distributor->id,
                'name' => $this->distributor->user?->name,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'items' => DistributorIssueItemResource::collection($this->whenLoaded('items')),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'approved_by' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'completed_by' => $this->whenLoaded('completer', fn () => $this->completer ? [
                'id' => $this->completer->id,
                'name' => $this->completer->name,
            ] : null),
            'corrections' => CorrectionResource::collection($this->whenLoaded('corrections')),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
