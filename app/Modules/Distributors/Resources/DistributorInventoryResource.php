<?php

namespace App\Modules\Distributors\Resources;

use App\Modules\Distributors\Models\DistributorInventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DistributorInventory
 */
class DistributorInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distributor_id' => $this->distributor_id,
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
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
