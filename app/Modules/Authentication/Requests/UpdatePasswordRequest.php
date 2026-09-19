<?php

namespace App\Modules\Authentication\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Rules\StrongPassword;

class UpdatePasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', new StrongPassword],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => __('validation.required', ['attribute' => 'current password']),
            'password.required' => __('validation.required', ['attribute' => 'password']),
            'password.confirmed' => __('validation.confirmed', ['attribute' => 'password']),
        ];
    }
}
