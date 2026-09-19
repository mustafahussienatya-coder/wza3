<?php

namespace App\Modules\Inventory\Exceptions;

class CannotCorrectMovementException extends InventoryException
{
    protected function errorCode(): string
    {
        return 'CANNOT_CORRECT_MOVEMENT';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'cannot_correct_movement';
    }
}
