<?php

namespace App\Modules\Inventory\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Inventory\Enums\StockMovementReason;
use Illuminate\Validation\Rule;

class StoreBulkStockInRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'rows.*.warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('status', 'active')],
            'rows.*.unit_id' => ['required', 'integer'],
            'rows.*.quantity' => ['required', 'numeric', 'gt:0'],
            'rows.*.unit_price' => ['required', 'numeric', 'min:0'],
            'rows.*.reason' => ['required', Rule::in(collect(StockMovementReason::cases())->pluck('value')->all())],
            'rows.*.moved_at' => ['nullable', 'date'],
            'rows.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'rows' => __('validation.attributes.rows'),
            'rows.*.product_id' => __('validation.attributes.product_id'),
            'rows.*.warehouse_id' => __('validation.attributes.warehouse_id'),
            'rows.*.unit_id' => __('validation.attributes.unit_id'),
            'rows.*.quantity' => __('validation.attributes.quantity'),
            'rows.*.unit_price' => __('validation.attributes.unit_price'),
            'rows.*.reason' => __('validation.attributes.reason'),
            'rows.*.moved_at' => __('validation.attributes.moved_at'),
            'rows.*.description' => __('validation.attributes.description'),
        ];
    }
}
