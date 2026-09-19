<?php

namespace App\Modules\Distributors\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Distributors\Enums\DistributorStatus;
use Illuminate\Validation\Rule;

class UpdateDistributorStatusRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(DistributorStatus::toArray())],
        ];
    }
}
