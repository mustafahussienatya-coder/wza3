<?php

namespace App\Modules\Customers\Requests;

use App\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Users\Models\User;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends BaseFormRequest
{
    public function rules(): array
    {
        /** @var User */
        $user = $this->user();
        $isDistributor = $user->hasRole(UserRole::DISTRIBUTOR->value);

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'secondary_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
            'distributor_id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('distributors', 'id'),
            ],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => [
                'nullable',
                Rule::in(['debit', 'credit']),
            ],
            'opening_balance_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        /** @var User */
        $user = $this->user();

        $validator->after(function ($validator) use ($user) {
            if ($user->hasRole(UserRole::DISTRIBUTOR->value)) {
                if ($this->input('distributor_id') !== null
                    && (int) $this->input('distributor_id') !== $user->distributor?->id) {
                    $validator->errors()->add('distributor_id', __('validation.in', ['attribute' => 'distributor_id']));
                }
            }
        });
    }
}
