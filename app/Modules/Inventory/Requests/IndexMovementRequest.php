<?php

namespace App\Modules\Inventory\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Inventory\Enums\StockMovementReason;
use App\Modules\Inventory\Enums\StockMovementType;
use Illuminate\Validation\Rule;

class IndexMovementRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::in(collect(StockMovementType::cases())->pluck('value')->all())],
            'reason' => ['nullable', Rule::in(collect(StockMovementReason::cases())->pluck('value')->all())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
