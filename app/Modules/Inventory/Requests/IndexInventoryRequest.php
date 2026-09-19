<?php

namespace App\Modules\Inventory\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class IndexInventoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['normal', 'low', 'out'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
