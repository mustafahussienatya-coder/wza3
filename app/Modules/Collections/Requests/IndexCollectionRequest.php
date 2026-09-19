<?php

namespace App\Modules\Collections\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Collections\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class IndexCollectionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'distributor_id' => 'nullable|integer|exists:distributors,id',
            'payment_method' => ['nullable', Rule::in(PaymentMethod::toArray())],
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
