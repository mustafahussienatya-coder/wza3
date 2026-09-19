<?php

namespace App\Modules\Products\Enums;

enum ProductStatus: string
{
    case ACTIVE = 'active';

    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
        };
    }
}
