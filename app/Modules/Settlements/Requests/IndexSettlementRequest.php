<?php

namespace App\Modules\Settlements\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Settlements\Enums\SettlementPaymentMethod;
use Illuminate\Validation\Rule;

class IndexSettlementRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'distributor_id' => 'nullable|integer|exists:distributors,id',
            'payment_method' => ['nullable', Rule::in(SettlementPaymentMethod::toArray())],
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
