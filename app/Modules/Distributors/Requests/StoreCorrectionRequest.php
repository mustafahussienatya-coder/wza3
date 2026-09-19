<?php

namespace App\Modules\Distributors\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreCorrectionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:distributor_issue_items,id'],
            'items.*.quantity' => ['sometimes', 'decimal:0,4', 'gt:0'],
            'items.*.unit_id' => ['sometimes', 'integer', 'exists:units,id'],
            'items.*.unit_price' => ['sometimes', 'decimal:0,2', 'gte:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'notes' => __('validation.attributes.notes'),
            'items' => __('validation.attributes.items'),
            'items.*.id' => __('validation.attributes.item'),
            'items.*.quantity' => __('validation.attributes.quantity'),
            'items.*.unit_id' => __('validation.attributes.unit_id'),
            'items.*.unit_price' => __('validation.attributes.unit_price'),
        ];
    }
}
