<?php

namespace App\Modules\Collections\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case CHEQUE = 'cheque';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::CHEQUE => 'Cheque',
            self::TRANSFER => 'Transfer',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::CASH => 'نقدي',
            self::CHEQUE => 'شيك',
            self::TRANSFER => 'تحويل',
        };
    }

    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
