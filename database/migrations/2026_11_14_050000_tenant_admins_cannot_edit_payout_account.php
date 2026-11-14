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

            $permissions = array_values(array_diff($permissions, ['payouts.account.manage']));

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Tenant roles must not retain the ability to change payout details.
    }
};
