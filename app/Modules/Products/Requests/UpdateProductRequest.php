<?php

namespace App\Modules\Products\Requests;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['name'] = ['required', 'string', 'max:255'];
        $rules['min_stock_level'] = ['sometimes', 'numeric', 'min:0'];
        $rules['base_unit_id'] = ['sometimes', 'prohibited'];

        $rules['units.*.barcode'] = [
            'nullable',
            'string',
            'max:255',
            Rule::unique('product_units', 'barcode')->ignore($this->route('product')->id, 'product_id'),
        ];

        return $rules;
    }
}
