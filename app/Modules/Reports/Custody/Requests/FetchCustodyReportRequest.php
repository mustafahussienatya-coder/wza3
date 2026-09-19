<?php

namespace App\Modules\Reports\Custody\Requests;

use App\Modules\Distributors\Enums\CustodyMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FetchCustodyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('custody.report');
    }

    public function rules(): array
    {
        return [
            'distributor_id' => ['nullable', 'integer', 'exists:distributors,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'movement_type' => ['nullable', Rule::in(CustodyMovementType::toArray())],
            'from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
