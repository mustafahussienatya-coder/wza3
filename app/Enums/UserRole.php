<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case DISTRIBUTOR = 'distributor';
    case CUSTOMER_SERVICE = 'customer_service';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::DISTRIBUTOR => 'Distributor',
            self::CUSTOMER_SERVICE => 'Customer Service',
        };
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'المدير العام',
            self::ADMIN => 'المدير',
            self::DISTRIBUTOR => 'الموزع',
            self::CUSTOMER_SERVICE => 'خدمة العملاء',
        };
    }

    public function permissions(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => [
                'users.view', 'users.create', 'users.update', 'users.delete',
                'roles.view', 'roles.manage',
                'permissions.view', 'permissions.manage',
                'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                'units.view', 'units.create', 'units.update', 'units.delete',
                'products.view', 'products.create', 'products.update', 'products.delete',
                'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
                'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.correct',
                'inventory.movements', 'inventory.count',
                'custody.view', 'custody.issue', 'custody.return', 'custody.transfer', 'custody.adjust',
                'custody.approve_disburse', 'custody.report',
                'distributors.view', 'distributors.update', 'distributors.delete',
                'areas.view', 'areas.create', 'areas.update', 'areas.delete',
                'customers.view', 'customers.create', 'customers.update', 'customers.delete',
                'customers.transfer', 'customers.change_status', 'customers.opening_balance',
                'customers.statement', 'customers.export',
                'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'orders.approve',
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.approve',
                'invoices.confirm', 'invoices.cancel',
                'payments.view', 'payments.create', 'payments.update', 'payments.delete',
                'settlements.view', 'settlements.create',
                'returns.view', 'returns.create', 'returns.approve',
                'reports.view', 'reports.export',
                'settings.view', 'settings.update',
                'audit_log.view',
            ],
            self::ADMIN => [
                'users.view', 'users.create', 'users.update',
                'categories.view', 'categories.create', 'categories.update', 'categories.delete',
                'units.view', 'units.create', 'units.update', 'units.delete',
                'products.view', 'products.create', 'products.update', 'products.delete',
                'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
                'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.correct',
                'inventory.movements', 'inventory.count',
                'custody.view', 'custody.issue', 'custody.return', 'custody.transfer', 'custody.adjust',
                'custody.approve_disburse', 'custody.report',
                'distributors.view', 'distributors.update', 'distributors.delete',
                'areas.view', 'areas.create', 'areas.update', 'areas.delete',
                'customers.view', 'customers.create', 'customers.update', 'customers.delete',
                'customers.transfer', 'customers.change_status', 'customers.opening_balance',
                'customers.statement', 'customers.export',
                'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'orders.approve',
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.approve',
                'invoices.confirm', 'invoices.cancel',
                'payments.view', 'payments.create', 'payments.update', 'payments.delete',
                'settlements.view', 'settlements.create',
                'returns.view', 'returns.create', 'returns.approve',
                'reports.view', 'reports.export',
            ],
            self::DISTRIBUTOR => [
                'areas.view',
                'customers.view', 'customers.create', 'customers.update',
                'customers.statement',
                'orders.view', 'orders.create',
                'invoices.view', 'invoices.create', 'invoices.update',
                'invoices.confirm', 'invoices.cancel',
                'payments.view', 'payments.create',
                'settlements.view', 'settlements.create',
                'returns.view', 'returns.create',
                'custody.view', 'custody.report',
                'inventory.view',
            ],
            self::CUSTOMER_SERVICE => [
                'customers.view',
                'distributors.view',
                'inventory.view',
            ],
        };
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
