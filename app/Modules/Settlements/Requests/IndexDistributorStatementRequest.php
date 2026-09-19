<?php

namespace App\Modules\Settlements\Requests;

use App\Http\Requests\BaseFormRequest;

class IndexDistributorStatementRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
