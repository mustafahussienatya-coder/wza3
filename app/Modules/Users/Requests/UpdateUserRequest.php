<?php

namespace App\Modules\Users\Requests;

use App\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Distributors\Enums\VehicleType;
use App\Rules\StrongPassword;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $roles = array_column(UserRole::cases(), 'value');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:50', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'confirmed', new StrongPassword],
            'role' => ['sometimes', Rule::in($roles)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'distributor' => ['sometimes', 'array', Rule::prohibitedIf(fn () => ! $this->targetIsDistributor())],
            'distributor.vehicle_number' => ['nullable', 'string', 'max:50'],
            'distributor.vehicle_type' => ['nullable', Rule::enum(VehicleType::class)],
            'distributor.national_id_photo_front' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'distributor.national_id_photo_back' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
            'distributor.area_ids' => ['sometimes', 'array'],
            'distributor.area_ids.*' => ['integer', 'exists:areas,id'],
        ];
    }

    private function targetIsDistributor(): bool
    {
        $user = $this->route('user');

        if ($user !== null && $user->role === UserRole::DISTRIBUTOR->value) {
            return true;
        }

        return $this->input('role') === UserRole::DISTRIBUTOR->value;
    }
}
