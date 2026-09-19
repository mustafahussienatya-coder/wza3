<?php

namespace App\Modules\Settlements\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResponsibilityStatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'distributor' => $this['distributor'],
            'goods_value' => $this['goods_value'],
            'custody_quantity' => $this['custody_quantity'],
            'collected_unsettled' => $this['collected_unsettled'],
            'total' => $this['total'],
            'per_product' => $this['per_product'],
        ];
    }
}
