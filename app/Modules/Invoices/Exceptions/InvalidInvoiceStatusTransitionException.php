<?php

namespace App\Modules\Invoices\Exceptions;

class InvalidInvoiceStatusTransitionException extends InvoiceException
{
    protected function errorCode(): string
    {
        return 'INVALID_INVOICE_STATUS_TRANSITION';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'invalid_status_transition';
    }
}
