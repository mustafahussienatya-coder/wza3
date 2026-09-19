<?php

namespace App\Modules\Distributors\Resources;

use App\Modules\Distributors\Models\CustodyMovement;
use App\Modules\Distributors\Models\DistributorIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustodyMovement
 */
class CustodyMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distributor_id' => $this->distributor_id,
            'movement_type' => $this->movement_type->value,
            'movement_type_label' => $this->movement_type->label(),
            'movement_type_label_ar' => $this->movement_type->labelAr(),
            'direction' => $this->movement_type->direction(),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'code' => $this->product->code,
                'base_unit' => $this->product->relationLoaded('baseUnit') && $this->product->baseUnit ? [
                    'id' => $this->product->baseUnit->id,
                    'name' => $this->product->baseUnit->name,
                    'symbol' => $this->product->baseUnit->symbol,
                ] : null,
            ]),
            'quantity' => bcadd($this->quantity, '0', 4),
            'base_quantity' => bcadd($this->base_quantity, '0', 4),
            'display_quantity' => bcadd($this->base_quantity, '0', 4),
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
            ]),
            'conversion_factor' => bcadd($this->conversion_factor, '0', 4),
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_number' => $this->when($this->reference_type === DistributorIssue::MOVEMENT_REFERENCE_TYPE, fn () => $this->whenLoaded('referenceIssue')
                ? ($this->referenceIssue?->issue_number ?? null)
                : null),
            'performed_by' => $this->whenLoaded('performedBy', fn () => $this->performedBy ? [
                'id' => $this->performedBy->id,
                'name' => $this->performedBy->name,
            ] : null),
            'reason' => $this->reason,
            'running_base_quantity' => $this->running_base_quantity !== null
                ? bcadd((string) $this->running_base_quantity, '0', 4)
                : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
