<?php

namespace App\Modules\Warehouses\Requests;

use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends StoreWarehouseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
