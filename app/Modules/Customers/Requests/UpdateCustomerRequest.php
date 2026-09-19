<?php

namespace App\Modules\Customers\Requests;

use App\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Users\Models\User;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends BaseFormRequest
{
    public function rules(): array
    {
        /** @var User */
        $user = $this->user();
        $customerId = $this->route('customer')?->id;

        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:30'],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
            'credit_limit' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $user->hasRole(UserRole::DISTRIBUTOR->value)) {
            $rules['distributor_id'] = ['nullable', 'integer', Rule::exists('distributors', 'id')];
        }

        return $rules;
    }
}
