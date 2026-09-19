<?php

namespace App\Modules\Authentication\Exceptions;

class InvalidCredentialsException extends AuthenticationException
{
    protected function errorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }

    protected function httpStatus(): int
    {
        return 401;
    }

    protected function translationKey(): string
    {
        return 'auth.invalid_credentials';
    }
}
