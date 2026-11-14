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

    public const PAYOUTS_MANAGE = 'payouts.manage';

    public const REPORTS_VIEW = 'reports.view';

    public const SETTINGS_MANAGE = 'settings.manage';

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
            self::PAYOUTS_MANAGE,
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
                    self::PAYOUTS_MANAGE,
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
