<?php

namespace App\Modules\Distributors\Resources;

use App\Modules\Distributors\Models\DistributorIssueCorrectionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DistributorIssueCorrectionItem
 */
class CorrectionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'issue_item_id' => $this->issue_item_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'code' => $this->product->code,
            ]),
            'correction_type' => $this->correction_type?->value,
            'correction_type_label' => $this->correction_type?->label(),
            'correction_type_label_ar' => $this->correction_type?->labelAr(),
            'original_unit_id' => $this->original_unit_id,
            'original_unit' => $this->whenLoaded('originalUnit', fn () => [
                'id' => $this->originalUnit->id,
                'name' => $this->originalUnit->name,
            ]),
            'corrected_unit_id' => $this->corrected_unit_id,
            'corrected_unit' => $this->whenLoaded('correctedUnit', fn () => [
                'id' => $this->correctedUnit->id,
                'name' => $this->correctedUnit->name,
            ]),
            'original_quantity' => bcadd($this->original_quantity, '0', 4),
            'corrected_quantity' => bcadd($this->corrected_quantity, '0', 4),
            'original_base_quantity' => bcadd($this->original_base_quantity, '0', 4),
            'corrected_base_quantity' => bcadd($this->corrected_base_quantity, '0', 4),
            'original_conversion_factor' => bcadd($this->original_conversion_factor, '0', 4),
            'corrected_conversion_factor' => bcadd($this->corrected_conversion_factor, '0', 4),
            'original_unit_price' => bcadd((string) ($this->original_unit_price ?? '0'), '0', 2),
            'corrected_unit_price' => bcadd((string) ($this->corrected_unit_price ?? '0'), '0', 2),
            'total_before' => bcadd((string) ($this->total_before ?? '0'), '0', 2),
            'total_after' => bcadd((string) ($this->total_after ?? '0'), '0', 2),
            'value_difference' => bcadd((string) ($this->value_difference ?? '0'), '0', 2),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
