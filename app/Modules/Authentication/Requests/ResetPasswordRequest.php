<?php

namespace App\Modules\Authentication\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Rules\StrongPassword;

class ResetPasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', new StrongPassword],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => __('validation.required', ['attribute' => 'email']),
            'email.email' => __('validation.email', ['attribute' => 'email']),
            'email.exists' => __('validation.exists', ['attribute' => 'email']),
            'token.required' => __('validation.required', ['attribute' => 'token']),
            'password.required' => __('validation.required', ['attribute' => 'password']),
            'password.confirmed' => __('validation.confirmed', ['attribute' => 'password']),
        ];
    }
}
