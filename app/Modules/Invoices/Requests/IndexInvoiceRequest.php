<?php

namespace App\Modules\Invoices\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\Invoices\Enums\InvoiceStatus;
use Illuminate\Validation\Rule;

class IndexInvoiceRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(InvoiceStatus::toArray())],
            'ownership' => ['nullable', Rule::in(['company', 'distributor'])],
            'customer_id' => 'nullable|integer|exists:customers,id',
            'sales_distributor_id' => 'nullable|integer|exists:distributors,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
