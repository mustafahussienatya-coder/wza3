<?php

namespace App\Modules\Customers\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class AdjustOpeningBalanceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['debit', 'credit'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
