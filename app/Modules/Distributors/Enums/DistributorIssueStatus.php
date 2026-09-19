<?php

namespace App\Modules\Distributors\Enums;

enum DistributorIssueStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case COMPLETED = 'completed';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::COMPLETED => 'Completed',
            self::RETURNED => 'Returned',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::PENDING_APPROVAL => 'بانتظار الاعتماد',
            self::APPROVED => 'معتمد',
            self::COMPLETED => 'مكتمل',
            self::RETURNED => 'مرتجع للمخزن',
            self::CANCELLED => 'ملغي',
        };
    }

    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
