<?php

namespace App\Modules\Distributors\Enums;

enum CustodyMovementType: string
{
    case ISSUE = 'issue';
    case SALE = 'sale';
    case CUSTOMER_RETURN = 'customer_return';
    case RETURN_TO_WAREHOUSE = 'return_to_warehouse';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case ADJUSTMENT = 'adjustment';

    public function direction(): string
    {
        return match ($this) {
            self::ISSUE, self::CUSTOMER_RETURN, self::TRANSFER_IN => 'in',
            self::SALE, self::RETURN_TO_WAREHOUSE, self::TRANSFER_OUT => 'out',
            self::ADJUSTMENT => 'adjust',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ISSUE => 'Distributor Issue',
            self::SALE => 'Sale',
            self::CUSTOMER_RETURN => 'Customer Return',
            self::RETURN_TO_WAREHOUSE => 'Return to Warehouse',
            self::TRANSFER_IN => 'Transfer In',
            self::TRANSFER_OUT => 'Transfer Out',
            self::ADJUSTMENT => 'Adjustment',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::ISSUE => 'تسليم للموزع',
            self::SALE => 'بيع من العهدة',
            self::CUSTOMER_RETURN => 'مرتجع من عميل',
            self::RETURN_TO_WAREHOUSE => 'مرتجع للمخزن',
            self::TRANSFER_IN => 'نقل وارد',
            self::TRANSFER_OUT => 'نقل صادر',
            self::ADJUSTMENT => 'تسوية عهدة',
        };
    }

    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
