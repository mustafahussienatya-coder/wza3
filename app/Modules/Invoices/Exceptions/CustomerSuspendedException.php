<?php

namespace App\Modules\Invoices\Exceptions;

class CustomerSuspendedException extends InvoiceException
{
    protected function errorCode(): string
    {
        return 'CUSTOMER_SUSPENDED';
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function translationKey(): string
    {
        return 'customer_suspended';
    }
}
