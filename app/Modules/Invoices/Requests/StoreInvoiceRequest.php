<?php

namespace App\Modules\Invoices\Requests;

use App\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Collections\Enums\PaymentMethod;
use App\Modules\Customers\Models\Customer;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.create');
    }

    public function rules(): array
    {
        $isDistributor = $this->user()->hasRole(UserRole::DISTRIBUTOR->value);

        $customerDistributionId = Customer::query()
            ->whereKey($this->input('customer_id'))
            ->value('distributor_id');

        $isCustodyInvoice = $isDistributor || $customerDistributionId !== null;

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'warehouse_id' => $isCustodyInvoice
                ? ['prohibited']
                : ['required', 'integer', 'exists:warehouses,id'],
            'invoice_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'confirm' => ['sometimes', 'boolean'],
            'collections' => ['sometimes', 'array', 'max:20'],
            'collections.*.amount' => ['required_with:collections', 'numeric', 'gt:0'],
            'collections.*.payment_method' => ['required_with:collections', Rule::in(PaymentMethod::toArray())],
            'collections.*.reference_no' => ['nullable', 'string', 'max:255'],
            'collections.*.collection_date' => ['nullable', 'date'],
            'collections.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
