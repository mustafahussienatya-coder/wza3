<?php

namespace App\Modules\Categories\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($categoryId)],
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
