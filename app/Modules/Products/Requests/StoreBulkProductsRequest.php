<?php

namespace App\Modules\Products\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreBulkProductsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*' => ['array'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.max' => __('validation.max.array', ['max' => 300]),
            'items.required' => __('validation.required'),
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => __('validation.attributes.items'),
        ];
    }
}
