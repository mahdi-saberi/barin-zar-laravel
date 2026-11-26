<?php

namespace App\Support;

use App\Models\User;

final class StaffAccess
{
    public const ROLES = [
        'customer' => 'مشتری',
        'catalog' => 'مدیر کالا و محتوا',
        'orders' => 'مسئول سفارش‌ها',
        'finance' => 'مسئول مالی',
        'admin' => 'مدیر فروشگاه',
    ];

    private const PERMISSIONS = [
        'catalog' => ['content'],
        'orders' => ['orders', 'fulfill', 'customers'],
        'finance' => ['orders', 'finance', 'customers'],
        'admin' => ['content', 'orders', 'fulfill', 'finance', 'customers', 'settings', 'gateways'],
    ];

    public static function owner(?User $user): bool
    {
        return $user && $user->email && strcasecmp($user->email, (string) config('barin.admin_email')) === 0;
    }

    public static function allows(?User $user, string $permission): bool
    {
        if (self::owner($user)) {
            return true;
        }

        $permissions = self::PERMISSIONS[$user?->role ?? 'customer'] ?? [];

        return $permission === 'dashboard' ? $permissions !== [] : in_array($permission, $permissions, true);
    }
}
