<?php

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inventory
 */
class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'code' => $this->product->code,
                'min_stock_level' => $this->product->min_stock_level,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'quantity' => bcadd($this->quantity, '0', 4),
            'status' => $this->stockStatus(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function stockStatus(): string
    {
        $min = $this->product?->min_stock_level ?? '0';

        if (bccomp($this->quantity, '0', 4) <= 0) {
            return 'out';
        }

        if (bccomp($this->quantity, $min, 4) < 0) {
            return 'low';
        }

        return 'normal';
    }
}
