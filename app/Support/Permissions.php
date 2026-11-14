<?php

namespace App\Support;

final class Permissions
{
    public const TENANTS_MANAGE = 'tenants.manage';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_MANAGE = 'roles.manage';

    public const EVENTS_MANAGE = 'events.manage';

    public const ORDERS_MANAGE = 'orders.manage';

    public const TICKETS_CHECKIN = 'tickets.checkin';

    public const PAYMENTS_VIEW = 'payments.view';

    public const PAYOUTS_VIEW = 'payouts.view';

    public const PAYOUTS_ACCOUNT_MANAGE = 'payouts.account.manage';

    public const PAYOUTS_MANAGE = 'payouts.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const PLATFORM_OVERVIEW = 'platform.overview';

    public const PLATFORM_TENANTS = 'platform.tenants';

    public const PLATFORM_PAYOUTS = 'platform.payouts';

    public const PLATFORM_USERS = 'platform.users';

    /**
     * @return list<string>
     */
    public static function platformPermissions(): array
    {
        return [
            self::PLATFORM_OVERVIEW,
            self::PLATFORM_TENANTS,
            self::PLATFORM_PAYOUTS,
            self::PLATFORM_USERS,
        ];
    }

    /**
     * @return array<string, array{name: string, description: string, permissions: list<string>, is_system: bool}>
     */
    public static function platformDefaultRoles(): array
    {
        return [
            'owner' => [
                'name' => 'Platform Owner',
                'description' => 'Full access to every platform console area.',
                'permissions' => self::platformPermissions(),
                'is_system' => true,
            ],
            'manager' => [
                'name' => 'Platform Manager',
                'description' => 'Manage organisers, payouts and see the platform overview. Cannot manage the platform team.',
                'permissions' => [self::PLATFORM_OVERVIEW, self::PLATFORM_TENANTS, self::PLATFORM_PAYOUTS],
                'is_system' => true,
            ],
            'finance' => [
                'name' => 'Platform Finance',
                'description' => 'Review and disburse payouts, see the platform overview.',
                'permissions' => [self::PLATFORM_OVERVIEW, self::PLATFORM_PAYOUTS],
                'is_system' => true,
            ],
            'viewer' => [
                'name' => 'Platform Viewer',
                'description' => 'Read-only access to the platform overview.',
                'permissions' => [self::PLATFORM_OVERVIEW],
                'is_system' => true,
            ],
        ];
    }

    /**
     * @return array<string, array{name: string, permissions: list<string>}>
     */
    public static function defaultRoles(): array
    {
        $all = [
            self::USERS_MANAGE,
            self::ROLES_MANAGE,
            self::EVENTS_MANAGE,
            self::ORDERS_MANAGE,
            self::TICKETS_CHECKIN,
            self::PAYMENTS_VIEW,
            self::PAYOUTS_VIEW,
            self::REPORTS_VIEW,
            self::SETTINGS_MANAGE,
        ];

        return [
            'admin' => [
                'name' => 'Administrator',
                'permissions' => $all,
            ],
            'manager' => [
                'name' => 'Event Manager',
                'permissions' => [
                    self::EVENTS_MANAGE,
                    self::ORDERS_MANAGE,
                    self::TICKETS_CHECKIN,
                    self::PAYMENTS_VIEW,
                    self::PAYOUTS_VIEW,
                    self::REPORTS_VIEW,
                ],
            ],
            'checkin' => [
                'name' => 'Check-in Agent',
                'permissions' => [self::TICKETS_CHECKIN],
            ],
            'attendee' => [
                'name' => 'Attendee',
                'permissions' => [],
            ],
        ];
    }
}
