<?php

namespace App\Modules\Invoices\Exceptions;

class InvalidInvoiceItemException extends InvoiceException
{
    protected function errorCode(): string
    {
        return 'INVALID_INVOICE_ITEM';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'invalid_item';
    }
}
