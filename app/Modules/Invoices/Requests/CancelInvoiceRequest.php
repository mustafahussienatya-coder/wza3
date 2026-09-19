<?php

namespace App\Modules\Invoices\Requests;

use App\Http\Requests\BaseFormRequest;

class CancelInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.cancel');
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
