<?php

namespace App\Modules\Collections\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Collections\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class StoreCollectionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payments.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(PaymentMethod::toArray())],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'collection_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
