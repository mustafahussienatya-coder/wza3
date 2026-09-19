<?php

namespace App\Modules\Distributors\Exceptions;

class InvalidCustodyOperationException extends CustodyException
{
    protected function errorCode(): string
    {
        return 'INVALID_CUSTODY_OPERATION';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'invalid_custody_operation';
    }
}
