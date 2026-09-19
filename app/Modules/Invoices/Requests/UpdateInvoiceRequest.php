<?php

namespace App\Modules\Invoices\Requests;

use App\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Invoices\Models\Invoice;

class UpdateInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.update');
    }

    public function rules(): array
    {
        $isDistributor = $this->user()->hasRole(UserRole::DISTRIBUTOR->value);

        $invoice = $this->route('invoice');
        $isCustodyInvoice = $isDistributor
            || ($invoice instanceof Invoice && $invoice->isDistributor());

        return [
            'invoice_date' => ['required', 'date'],
            'warehouse_id' => $isCustodyInvoice
                ? ['prohibited']
                : ['nullable', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
