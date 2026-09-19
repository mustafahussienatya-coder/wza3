<?php

namespace App\Modules\Collections\Exceptions;

class PaymentExceedsOutstandingException extends CollectionException
{
    protected function errorCode(): string
    {
        return 'PAYMENT_EXCEEDS_OUTSTANDING';
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function translationKey(): string
    {
        return 'exceeds_outstanding';
    }
}
