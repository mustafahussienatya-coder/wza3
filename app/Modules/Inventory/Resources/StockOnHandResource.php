<?php

namespace App\Modules\Inventory\Resources;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class StockOnHandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $quantities = $this->whenLoaded('inventories', fn () => $this->inventories
            ->map(fn ($inventory) => [
                'warehouse_id' => $inventory->warehouse_id,
                'warehouse_name' => $inventory->warehouse?->name ?? '',
                'quantity' => bcadd($inventory->quantity, '0', 4),
            ])
            ->values());

        $total = '0';
        if ($this->relationLoaded('inventories')) {
            foreach ($this->inventories as $inventory) {
                $total = bcadd($total, $inventory->quantity, 4);
            }
        }

        return [
            'product' => [
                'id' => $this->id,
                'name' => $this->name,
                'code' => $this->code,
                'base_unit' => $this->whenLoaded('baseUnit', fn () => [
                    'id' => $this->baseUnit->id,
                    'name' => $this->baseUnit->name,
                    'symbol' => $this->baseUnit->symbol,
                ]),
            ],
            'units' => $this->whenLoaded('units', fn () => $this->units
                ->map(fn (ProductUnit $productUnit) => [
                    'unit_id' => $productUnit->unit_id,
                    'name' => $productUnit->unit?->name ?? '',
                    'symbol' => $productUnit->unit?->symbol,
                    'conversion_factor' => bcadd($productUnit->conversion_factor, '0', 4),
                    'selling_price' => bcadd($productUnit->selling_price, '0', 2),
                    'cost_price' => bcadd($productUnit->cost_price, '0', 2),
                ])
                ->values()),
            'quantities' => $quantities,
            'total' => $total,
            'latest_unit_cost' => $this->latest_unit_cost !== null
                ? bcadd((string) $this->latest_unit_cost, '0', 2)
                : null,
            'min_stock_level' => bcadd($this->min_stock_level, '0', 3),
            'status' => $this->stockStatus($total),
        ];
    }

    private function stockStatus(string $total): string
    {
        $min = bcadd($this->min_stock_level, '0', 3);

        if (bccomp($total, '0', 4) <= 0) {
            return 'out';
        }

        if (bccomp($total, $min, 4) < 0) {
            return 'low';
        }

        return 'normal';
    }
}
