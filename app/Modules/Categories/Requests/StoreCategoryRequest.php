<?php

namespace App\Modules\Categories\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
