<?php

namespace App\Modules\Customers\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class TransferCustomerRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'to_distributor_id' => ['required', 'integer', 'distinct', Rule::exists('distributors', 'id')],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
