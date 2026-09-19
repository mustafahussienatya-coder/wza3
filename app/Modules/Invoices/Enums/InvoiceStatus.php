<?php

namespace App\Modules\Invoices\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::CONFIRMED => 'محسومة',
            self::CANCELLED => 'ملغاة',
        };
    }

    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
