<?php

namespace App\Modules\Distributors\Resources;

use App\Modules\Distributors\Models\DistributorIssueItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DistributorIssueItem
 */
class DistributorIssueItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
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
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
            ]),
            'quantity' => bcadd($this->quantity, '0', 4),
            'base_quantity' => bcadd($this->base_quantity, '0', 4),
            'conversion_factor' => bcadd($this->conversion_factor, '0', 4),
            'unit_price' => bcadd((string) ($this->unit_price ?? '0'), '0', 2),
        ];
    }
}
