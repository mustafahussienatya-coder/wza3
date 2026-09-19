<?php

namespace App\Modules\Settlements\Exceptions;

class InvalidSettlementOperationException extends SettlementException
{
    protected function errorCode(): string
    {
        return 'INVALID_SETTLEMENT_OPERATION';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'invalid_operation';
    }
}
