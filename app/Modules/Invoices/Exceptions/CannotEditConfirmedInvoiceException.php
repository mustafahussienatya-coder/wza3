<?php

namespace App\Modules\Invoices\Exceptions;

class CannotEditConfirmedInvoiceException extends InvoiceException
{
    protected function errorCode(): string
    {
        return 'CANNOT_EDIT_CONFIRMED_INVOICE';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'cannot_edit_confirmed_invoice';
    }
}
