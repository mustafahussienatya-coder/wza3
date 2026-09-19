<?php

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\StockBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockBatch
 */
class StockBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_no' => $this->batch_no,
            'received_at' => $this->received_at?->toIso8601String(),
            'quantity' => bcadd($this->quantity, '0', 4),
            'remaining' => bcadd($this->remaining, '0', 4),
            'unit_cost' => bcadd($this->unit_cost, '0', 2),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'code' => $this->product->code,
                'base_unit' => $this->whenLoaded('product.baseUnit', fn () => [
                    'id' => $this->product->baseUnit->id,
                    'name' => $this->product->baseUnit->name,
                    'symbol' => $this->product->baseUnit->symbol,
                ]),
            ]),
        ];
    }
}
