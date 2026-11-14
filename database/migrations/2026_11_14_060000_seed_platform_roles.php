<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Permissions::platformDefaultRoles() as $slug => $role) {
            DB::table('roles')->updateOrInsert(
                ['tenant_id' => null, 'slug' => $slug],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'permissions' => json_encode($role['permissions']),
                    'is_system' => $role['is_system'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereNull('tenant_id')->whereIn('slug', array_keys(Permissions::platformDefaultRoles()))->delete();
    }
};
