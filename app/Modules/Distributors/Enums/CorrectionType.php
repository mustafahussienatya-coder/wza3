<?php

namespace App\Modules\Distributors\Enums;

enum CorrectionType: string
{
    case QUANTITY = 'quantity';
    case UNIT = 'unit';
    case PRICE = 'price';
    case MIXED = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::QUANTITY => 'Quantity',
            self::UNIT => 'Unit',
            self::PRICE => 'Price',
            self::MIXED => 'Mixed',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::QUANTITY => 'كمية',
            self::UNIT => 'وحدة',
            self::PRICE => 'سعر',
            self::MIXED => 'مختلط',
        };
    }

    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
