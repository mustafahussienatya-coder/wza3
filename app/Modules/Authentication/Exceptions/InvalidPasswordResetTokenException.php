<?php

namespace App\Modules\Authentication\Exceptions;

class InvalidPasswordResetTokenException extends AuthenticationException
{
    protected function errorCode(): string
    {
        return 'INVALID_RESET_TOKEN';
    }

    protected function httpStatus(): int
    {
        return 400;
    }

    protected function translationKey(): string
    {
        return 'auth.invalid_reset_token';
    }
}
