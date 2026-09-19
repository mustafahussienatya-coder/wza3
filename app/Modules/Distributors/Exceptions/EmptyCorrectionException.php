<?php

namespace App\Modules\Distributors\Exceptions;

class EmptyCorrectionException extends CustodyException
{
    protected function errorCode(): string
    {
        return 'EMPTY_CORRECTION';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'empty_correction';
    }
}
