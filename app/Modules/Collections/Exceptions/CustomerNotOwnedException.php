<?php

namespace App\Modules\Collections\Exceptions;

class CustomerNotOwnedException extends CollectionException
{
    protected function errorCode(): string
    {
        return 'CUSTOMER_NOT_OWNED';
    }

    protected function httpStatus(): int
    {
        return 403;
    }

    protected function translationKey(): string
    {
        return 'customer_not_owned';
    }
}
