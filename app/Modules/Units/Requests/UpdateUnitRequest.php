<?php

namespace App\Modules\Units\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $unitId = $this->route('unit')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('units', 'name')->ignore($unitId)],
            'symbol' => ['nullable', 'string', 'max:20'],
            'decimal_places' => ['sometimes', 'integer', 'min:0', 'max:3'],
            'is_weight' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
