<?php

namespace App\Modules\Inventory\Exceptions;

class InvalidStockOperationException extends InventoryException
{
    protected function errorCode(): string
    {
        return 'INVALID_STOCK_OPERATION';
    }

    protected function httpStatus(): int
    {
        return 422;
    }

    protected function translationKey(): string
    {
        return 'invalid_stock_operation';
    }
}
