<?php

namespace App\Modules\Customers\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ChangeCustomerStatusRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'الحالة',
        ];
    }
}
