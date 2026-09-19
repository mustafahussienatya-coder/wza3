<?php

namespace App\Modules\Distributors\Enums;

enum VehicleType: string
{
    case CAR = 'car';
    case MOTORCYCLE = 'motorcycle';
    case TUK_TUK = 'tuk_tuk';
    case PICKUP = 'pickup';

    public function label(): string
    {
        return match ($this) {
            self::CAR => 'Car',
            self::MOTORCYCLE => 'Motorcycle',
            self::TUK_TUK => 'Tuk Tuk',
            self::PICKUP => 'Pickup',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::CAR => 'سيارة',
            self::MOTORCYCLE => 'موتوسيكل',
            self::TUK_TUK => 'توك توك',
            self::PICKUP => 'بيك أب',
        };
    }
}
