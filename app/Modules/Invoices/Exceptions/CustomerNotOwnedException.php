<?php

namespace App\Modules\Invoices\Exceptions;

class CustomerNotOwnedException extends InvoiceException
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
