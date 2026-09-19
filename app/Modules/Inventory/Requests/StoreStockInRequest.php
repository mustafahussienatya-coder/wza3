<?php

namespace App\Modules\Inventory\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Inventory\Enums\StockMovementReason;
use Illuminate\Validation\Rule;

class StoreStockInRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('status', 'active')],
            'unit_id' => ['required', 'integer', Rule::exists('product_units', 'unit_id')
                ->where('product_id', $this->integer('product_id'))
                ->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', Rule::in(collect(StockMovementReason::cases())->pluck('value')->all())],
            'moved_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
