<?php

namespace App\Modules\Inventory\Resources;

use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_no' => $this->movement_no,
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
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'type_label_ar' => $this->type->labelAr(),
            'direction' => $this->type->direction(),
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->label(),
            'reason_label_ar' => $this->reason->labelAr(),
            'quantity' => bcadd($this->quantity, '0', 4),
            'base_quantity' => bcadd($this->quantity, '0', 4),
            'display_quantity' => bccomp((string) $this->conversion_factor, '0', 4) > 0
                ? bcdiv((string) $this->quantity, (string) $this->conversion_factor, 4)
                : bcadd((string) $this->quantity, '0', 4),
            'unit' => $this->whenLoaded('unit', fn () => [
                'id' => $this->unit->id,
                'name' => $this->unit->name,
            ]),
            'conversion_factor' => bcadd($this->conversion_factor, '0', 4),
            'unit_price' => $this->unit_price !== null ? bcadd($this->unit_price, '0', 2) : null,
            'distributor' => $this->whenLoaded('distributor', fn () => $this->when($this->distributor, [
                'id' => $this->distributor->id,
                'name' => $this->distributor->user?->name,
            ])),
            'from_warehouse' => $this->whenLoaded('fromWarehouse', fn () => $this->when($this->fromWarehouse, [
                'id' => $this->fromWarehouse->id,
                'name' => $this->fromWarehouse->name,
            ])),
            'to_warehouse' => $this->whenLoaded('toWarehouse', fn () => $this->when($this->toWarehouse, [
                'id' => $this->toWarehouse->id,
                'name' => $this->toWarehouse->name,
            ])),
            'reference_type' => $this->reference_type,
            'reference_no' => $this->reference_no,
            'invoice_reference_id' => $this->invoice_reference_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->role,
            ]),
            'moved_at' => $this->moved_at?->toISOString(),
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
