<?php

namespace App\Modules\Inventory\Enums;

enum StockMovementReason: string
{
    case OPENING_BALANCE = 'opening_balance';
    case PURCHASE_ORDER = 'purchase_order';
    case SALE_ORDER = 'sale_order';
    case CUSTOMER_RETURN = 'customer_return';
    case TRANSFER = 'transfer';
    case SUPPLIER_RETURN = 'supplier_return';
    case ADJUSTMENT = 'adjustment';
    case PURCHASE_RETURN = 'purchase_return';
    case SALE_RETURN = 'sale_return';
    case ENTRY_ERROR = 'entry_error';
    case DISTRIBUTOR_ISSUE = 'distributor_issue';
    case CUSTODY_RETURN = 'custody_return';

    public function label(): string
    {
        return match ($this) {
            self::OPENING_BALANCE => 'Opening Balance',
            self::PURCHASE_ORDER => 'Purchase Order',
            self::SALE_ORDER => 'Sale Order',
            self::CUSTOMER_RETURN => 'Customer Return',
            self::TRANSFER => 'Transfer',
            self::SUPPLIER_RETURN => 'Supplier Return',
            self::ADJUSTMENT => 'Adjustment',
            self::PURCHASE_RETURN => 'Purchase Return',
            self::SALE_RETURN => 'Sale Return',
            self::ENTRY_ERROR => 'Entry Error',
            self::DISTRIBUTOR_ISSUE => 'Distributor Issue',
            self::CUSTODY_RETURN => 'Custody Return',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::OPENING_BALANCE => 'رصيد افتتاحي',
            self::PURCHASE_ORDER => 'أمر شراء',
            self::SALE_ORDER => 'أمر بيع',
            self::CUSTOMER_RETURN => 'مرتجعات عملاء',
            self::TRANSFER => 'تحويل',
            self::SUPPLIER_RETURN => 'مرتجعات موردين',
            self::ADJUSTMENT => 'تصحيح رصيد',
            self::PURCHASE_RETURN => 'مرتجع أمر شراء',
            self::SALE_RETURN => 'مرتجع أمر بيع',
            self::ENTRY_ERROR => 'خطأ إدخال',
            self::DISTRIBUTOR_ISSUE => 'صرف عهدة',
            self::CUSTODY_RETURN => 'مرتجع عهدة',
        };
    }
}
