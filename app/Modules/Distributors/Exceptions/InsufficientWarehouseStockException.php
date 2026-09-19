<?php

namespace App\Modules\Distributors\Exceptions;

class InsufficientWarehouseStockException extends CustodyException
{
    protected function errorCode(): string
    {
        return 'INSUFFICIENT_WAREHOUSE_STOCK';
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function translationKey(): string
    {
        return 'insufficient_warehouse_stock';
    }
}
