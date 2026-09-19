<?php

namespace App\Modules\Customers\Enums;

enum LedgerTransactionType: string
{
    case OPENING_BALANCE = 'opening_balance';
    case SALE = 'sale';
    case PAYMENT = 'payment';
    case RETURN = 'return';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::OPENING_BALANCE => 'Opening Balance',
            self::SALE => 'Sale',
            self::PAYMENT => 'Payment',
            self::RETURN => 'Return',
            self::ADJUSTMENT => 'Adjustment',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::OPENING_BALANCE => 'الرصيد الافتتاحي',
            self::SALE => 'بيع',
            self::PAYMENT => 'دفعة',
            self::RETURN => 'مرتجع',
            self::ADJUSTMENT => 'تسوية',
        };
    }
}
