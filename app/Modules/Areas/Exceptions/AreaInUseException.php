<?php

namespace App\Modules\Areas\Exceptions;

class AreaInUseException extends AreasException
{
    protected function errorCode(): string
    {
        return 'AREA_IN_USE';
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function translationKey(): string
    {
        return 'area.in_use';
    }
}
