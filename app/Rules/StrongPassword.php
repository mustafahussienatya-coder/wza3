<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (strlen($password) < 8) {
            $fail(__('validation.password.min', ['attribute' => $attribute]));
        }

        if (! preg_match('/[A-Z]/', $password)) {
            $fail(__('validation.password.uppercase', ['attribute' => $attribute]));
        }

        if (! preg_match('/[a-z]/', $password)) {
            $fail(__('validation.password.lowercase', ['attribute' => $attribute]));
        }

        if (! preg_match('/\d/', $password)) {
            $fail(__('validation.password.number', ['attribute' => $attribute]));
        }

        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            $fail(__('validation.password.special', ['attribute' => $attribute]));
        }
    }
}
