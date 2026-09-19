<?php

namespace App\Modules\Invoices\Exceptions;

class InvalidInvoiceQuantityException extends InvoiceException
{
    protected function errorCode(): string
    {
        return 'INVALID_INVOICE_QUANTITY';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'invalid_quantity';
    }
}
