<?php

namespace App\Modules\Distributors\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Distributors\Enums\CustodyMovementType;
use Illuminate\Validation\Rule;

class IndexCustodyStatementRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'movement_type' => ['nullable', Rule::in(CustodyMovementType::toArray())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
