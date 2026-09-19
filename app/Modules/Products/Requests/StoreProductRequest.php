<?php

namespace App\Modules\Products\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('status', 'active'),
            ],
            'base_unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where('is_active', true),
            ],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'units' => ['sometimes', 'array'],
            'units.*.unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where('is_active', true),
            ],
            'units.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'units.*.selling_price' => ['required', 'numeric', 'min:0'],
            'units.*.cost_price' => ['required', 'numeric', 'min:0'],
            'units.*.barcode' => ['nullable', 'string', 'max:255', Rule::unique('product_units', 'barcode')],
            'units.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
