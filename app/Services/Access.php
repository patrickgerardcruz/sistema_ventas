<?php

namespace App\Services;

class Access
{
    public const ROLES = ['admin' => 'Administrador', 'seller' => 'Vendedor', 'cashier' => 'Cajero'];

    public const GRANTS = [
        'admin' => ['dashboard', 'products', 'inventory', 'categories', 'brands', 'customers', 'suppliers', 'purchases', 'sales', 'cash', 'reports', 'returns', 'audit', 'users', 'settings', 'backups'],
        'seller' => ['dashboard', 'products', 'customers', 'sales'],
        'cashier' => ['dashboard', 'products', 'customers', 'sales', 'cash', 'returns'],
    ];

    public static function allows(string $module): bool
    {
        return in_array($module, self::GRANTS[request()->attributes->get('storeUser')?->role ?? ''] ?? [], true);
    }

    public static function check(string $module): void
    {
        abort_unless(self::allows($module), 403, 'No tienes permiso para esta operación.');
    }
}
