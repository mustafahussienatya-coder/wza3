<?php

namespace App\Modules\Settlements\Requests;

use App\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Settlements\Enums\SettlementPaymentMethod;
use Illuminate\Validation\Rule;

class StoreSettlementRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settlements.create');
    }

    public function rules(): array
    {
        return [
            'distributor_id' => [
                Rule::requiredIf(fn () => ! $this->user()->hasRole(UserRole::DISTRIBUTOR->value)),
                'integer',
                'exists:distributors,id',
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(SettlementPaymentMethod::toArray())],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'settlement_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
