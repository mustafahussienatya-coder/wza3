<?php

namespace App\Modules\Units\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreUnitRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:units,name'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'decimal_places' => ['sometimes', 'integer', 'min:0', 'max:3'],
            'is_weight' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
