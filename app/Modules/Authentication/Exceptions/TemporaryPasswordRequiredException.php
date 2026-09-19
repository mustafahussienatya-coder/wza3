<?php

namespace App\Modules\Authentication\Exceptions;

class TemporaryPasswordRequiredException extends AuthenticationException
{
    protected function errorCode(): string
    {
        return 'TEMP_PASSWORD_REQUIRED';
    }

    protected function httpStatus(): int
    {
        return 403;
    }

    protected function translationKey(): string
    {
        return 'auth.required_temporary_password_change';
    }
}
