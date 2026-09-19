<?php

namespace App\Modules\Distributors\Enums;

enum DistributorStatus: string
{
    case ACTIVE = 'active';

    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::ACTIVE => 'نشط',
            self::SUSPENDED => 'موقوف العمليات',
        };
    }

    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
