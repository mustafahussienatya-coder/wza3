<?php

namespace App\Modules\Authentication\Exceptions;

class AccountDeactivatedException extends AuthenticationException
{
    protected function errorCode(): string
    {
        return 'ACCOUNT_DEACTIVATED';
    }

    protected function httpStatus(): int
    {
        return 403;
    }

    protected function translationKey(): string
    {
        return 'auth.account_deactivated';
    }
}
