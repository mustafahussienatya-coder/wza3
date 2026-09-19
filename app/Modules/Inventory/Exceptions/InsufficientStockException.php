<?php

namespace App\Modules\Inventory\Exceptions;

class InsufficientStockException extends InventoryException
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
        return 'INSUFFICIENT_STOCK';
    }

    protected function httpStatus(): int
    {
        return 409;
    }

    protected function translationKey(): string
    {
        return 'insufficient_stock';
    }
}
