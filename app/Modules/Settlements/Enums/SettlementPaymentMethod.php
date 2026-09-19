<?php

namespace App\Modules\Settlements\Enums;

enum SettlementPaymentMethod: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::TRANSFER => 'Bank Transfer',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::CASH => 'نقدي',
            self::TRANSFER => 'تحويل بنكي',
        };
    }

    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
