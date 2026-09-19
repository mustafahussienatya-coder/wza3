<?php

namespace App\Modules\Settlements\Exceptions;

class SettlementExceedsCollectedException extends SettlementException
{
    protected function errorCode(): string
    {
        return 'SETTLEMENT_EXCEEDS_COLLECTED';
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function translationKey(): string
    {
        return 'exceeds_collected';
    }
}
