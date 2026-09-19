<?php

namespace App\Modules\Areas\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreAreaRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:areas,name'],
        ];
    }
}
