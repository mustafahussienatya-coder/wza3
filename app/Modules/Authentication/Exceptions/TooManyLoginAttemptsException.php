<?php

namespace App\Modules\Authentication\Exceptions;

class TooManyLoginAttemptsException extends AuthenticationException
{
    protected function errorCode(): string
    {
        return 'TOO_MANY_LOGIN_ATTEMPTS';
    }

    protected function httpStatus(): int
    {
        return 429;
    }

    protected function translationKey(): string
    {
        return 'auth.too_many_attempts';
    }
}
