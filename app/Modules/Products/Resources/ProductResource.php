<?php

namespace App\Modules\Products\Resources;

use App\Modules\Products\Models\Product;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'min_stock_level' => $this->min_stock_level,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_label_ar' => $this->status->labelAr(),
            'category_id' => $this->category_id,
            'total_stock' => bcadd((string) ($this->total_stock ?? 0), '0', 4),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'base_unit_id' => $this->base_unit_id,
            'base_unit' => $this->whenLoaded('baseUnit', fn () => [
                'id' => $this->baseUnit->id,
                'name' => $this->baseUnit->name,
                'symbol' => $this->baseUnit->symbol,
            ]),
            'units' => $this->whenLoaded('units', function () {
                return $this->units->map(fn ($unit) => [
                    'id' => $unit->id,
                    'unit_id' => $unit->unit_id,
                    'unit_name' => $unit->unit->name,
                    'unit_symbol' => $unit->unit->symbol,
                    'conversion_factor' => $unit->conversion_factor,
                    'selling_price' => $unit->selling_price,
                    'cost_price' => $unit->cost_price,
                    'barcode' => $unit->barcode,
                    'is_active' => $unit->is_active,
                ])->values();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
