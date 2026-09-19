<?php

namespace App\Modules\Inventory\Enums;

enum StockMovementType: string
{
    case OPENING_BALANCE = 'opening_balance';
    case STOCK_IN = 'stock_in';
    case STOCK_OUT = 'stock_out';
    case TRANSFER_OUT = 'transfer_out';
    case TRANSFER_IN = 'transfer_in';
    case SALE = 'sale';
    case DISTRIBUTOR_ISSUE = 'distributor_issue';
    case CUSTODY_RETURN = 'custody_return';
    case CUSTOMER_RETURN = 'customer_return';
    case SUPPLIER_RETURN = 'supplier_return';
    case PURCHASE_RETURN = 'purchase_return';
    case SALE_RETURN = 'sale_return';
    case STOCK_ADJUSTMENT = 'stock_adjustment';

    public function direction(): string
    {
        return match ($this) {
            self::STOCK_IN, self::OPENING_BALANCE, self::TRANSFER_IN,
            self::CUSTODY_RETURN, self::CUSTOMER_RETURN, self::SUPPLIER_RETURN, self::SALE_RETURN => 'in',
            self::STOCK_OUT, self::TRANSFER_OUT, self::SALE, self::DISTRIBUTOR_ISSUE,
            self::PURCHASE_RETURN => 'out',
            self::STOCK_ADJUSTMENT => 'adjust',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OPENING_BALANCE => 'Opening Balance',
            self::STOCK_IN => 'Stock In',
            self::STOCK_OUT => 'Stock Out',
            self::TRANSFER_OUT => 'Transfer Out',
            self::TRANSFER_IN => 'Transfer In',
            self::SALE => 'Sale',
            self::DISTRIBUTOR_ISSUE => 'Distributor Issue',
            self::CUSTODY_RETURN => 'Custody Return',
            self::CUSTOMER_RETURN => 'Customer Return',
            self::SUPPLIER_RETURN => 'Supplier Return',
            self::PURCHASE_RETURN => 'Purchase Return',
            self::SALE_RETURN => 'Sale Return',
            self::STOCK_ADJUSTMENT => 'Stock Adjustment',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::OPENING_BALANCE => 'رصيد افتتاحي',
            self::STOCK_IN => 'إدخال مخزون',
            self::STOCK_OUT => 'إخراج مخزون',
            self::TRANSFER_OUT => 'تحويل خارج',
            self::TRANSFER_IN => 'تحويل وارد',
            self::SALE => 'بيع',
            self::DISTRIBUTOR_ISSUE => 'صرف للموزع',
            self::CUSTODY_RETURN => 'مرتجع عهدة',
            self::CUSTOMER_RETURN => 'مرتجع عميل',
            self::SUPPLIER_RETURN => 'مرتجع مورد',
            self::PURCHASE_RETURN => 'مرتجع أمر شراء',
            self::SALE_RETURN => 'مرتجع أمر بيع',
            self::STOCK_ADJUSTMENT => 'تصحيح رصيد',
        };
    }
}
