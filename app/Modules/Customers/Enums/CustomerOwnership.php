<?php

namespace App\Modules\Customers\Enums;

enum CustomerOwnership: string
{
    case COMPANY = 'company';
    case DISTRIBUTOR = 'distributor';

    public function label(): string
    {
        return match ($this) {
            self::COMPANY => 'Company',
            self::DISTRIBUTOR => 'Distributor',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::COMPANY => 'الشركة',
            self::DISTRIBUTOR => 'الموزع',
        };
    }
}
