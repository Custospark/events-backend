<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->get()->each(function ($role): void {
            $permissions = json_decode((string) $role->permissions, true);

            if (! is_array($permissions)) {
                $permissions = [];
            }

            $hadPayoutManage = in_array('payouts.manage', $permissions, true);
            $permissions = array_values(array_diff($permissions, ['payouts.manage']));

            if ($hadPayoutManage || in_array($role->slug, ['admin', 'manager'], true)) {
                $permissions[] = 'payouts.view';
            }

            if ($role->slug === 'admin') {
                $permissions[] = 'settings.manage';
            }

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_unique($permissions))),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('roles')->get()->each(function ($role): void {
            $permissions = json_decode((string) $role->permissions, true);

            if (! is_array($permissions)) {
                $permissions = [];
            }

            if (in_array($role->slug, ['admin', 'manager'], true)) {
                $permissions = array_diff($permissions, ['payouts.view', 'payouts.account.manage']);
                $permissions[] = 'payouts.manage';
            }

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_unique($permissions))),
                'updated_at' => now(),
            ]);
        });
    }
};
