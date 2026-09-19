<?php

namespace App\Modules\Users\Requests;

use App\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Distributors\Enums\VehicleType;
use App\Rules\StrongPassword;
use Illuminate\Validation\Rule;

class StoreUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $roles = array_column(UserRole::cases(), 'value');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', new StrongPassword],
            'password_confirmation' => ['required', 'string'],
            'role' => ['required', Rule::in($roles)],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'distributor' => ['prohibited_unless:role,distributor', 'array'],
            'distributor.vehicle_number' => ['nullable', 'string', 'max:50'],
            'distributor.vehicle_type' => ['nullable', Rule::enum(VehicleType::class)],
            'distributor.national_id_photo_front' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'distributor.national_id_photo_back' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'distributor.area_ids' => ['sometimes', 'array'],
            'distributor.area_ids.*' => ['integer', 'exists:areas,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => $this->username ?? null,
        ]);
    }
}
