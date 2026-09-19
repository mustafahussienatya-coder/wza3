<?php

namespace App\Modules\Distributors\Exceptions;

class InsufficientDistributorStockException extends CustodyException
{
    public function __construct(
        ?string $product = null,
        ?string $available = null,
        ?string $required = null,
        ?string $unit = null,
    ) {
        parent::__construct(array_filter([
            'product' => $product,
            'available' => $available,
            'required' => $required,
            'unit' => $unit,
        ], static fn ($value) => $value !== null));
    }

    protected function errorCode(): string
    {
        return 'INSUFFICIENT_DISTRIBUTOR_STOCK';
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function translationKey(): string
    {
        return 'insufficient_distributor_stock';
    }
}
