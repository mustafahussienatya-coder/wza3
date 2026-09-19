<?php

namespace App\Modules\Distributors\Enums;

enum CorrectionStatus: string
{
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::COMPLETED => 'Completed',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::COMPLETED => 'مكتمل',
        };
    }

    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
