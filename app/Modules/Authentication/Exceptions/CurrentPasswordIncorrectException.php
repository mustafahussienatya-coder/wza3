<?php

namespace App\Modules\Authentication\Exceptions;

class CurrentPasswordIncorrectException extends AuthenticationException
{
    protected function errorCode(): string
    {
        return 'CURRENT_PASSWORD_INCORRECT';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'auth.current_password_incorrect';
    }
}
