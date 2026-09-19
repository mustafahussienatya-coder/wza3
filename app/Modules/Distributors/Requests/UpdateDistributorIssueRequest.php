<?php

namespace App\Modules\Distributors\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateDistributorIssueRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'distributor_id' => ['required', 'integer', Rule::exists('distributors', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'items.*.quantity' => ['required', 'decimal:0,4', 'gt:0'],
            'items.*.unit_price' => ['required', 'decimal:0,2', 'gte:0'],
        ];
    }
}
