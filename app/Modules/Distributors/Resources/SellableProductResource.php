<?php

namespace App\Modules\Distributors\Resources;

use App\Modules\Distributors\Models\DistributorInventory;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DistributorInventory
 */
class SellableProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->product;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'base_unit_id' => $product->base_unit_id,
            'base_unit' => [
                'id' => $product->baseUnit?->id,
                'name' => $product->baseUnit?->name,
                'symbol' => $product->baseUnit?->symbol,
            ],
            'units' => $product->activeUnits->map(fn ($unit) => [
                'id' => $unit->id,
                'unit_id' => $unit->unit_id,
                'unit_name' => $unit->unit->name,
                'unit_symbol' => $unit->unit->symbol,
                'conversion_factor' => $unit->conversion_factor,
                'selling_price' => $unit->selling_price,
                'cost_price' => $unit->cost_price,
                'barcode' => $unit->barcode,
                'is_active' => $unit->is_active,
            ])->values(),
            'available_quantity' => bcadd((string) $this->quantity, '0', 4),
            'custody_unit_price' => isset($this->custody_unit_price)
                ? bcadd((string) $this->custody_unit_price, '0', 2)
                : null,
        ];
    }
}