<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Contracts\TenantServiceInterface;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $platformPassword = env('SEED_PLATFORM_PASSWORD', 'Custospark2027!');

        User::query()->updateOrCreate(
            ['email' => env('SEED_PLATFORM_EMAIL', 'oscar@custospark.com')],
            [
                'tenant_id' => null,
                'role_id' => null,
                'name' => 'Custospark Platform Admin',
                'password' => Hash::make($platformPassword),
                'status' => 'active',
                'is_platform_admin' => true,
            ],
        );

        $tenant = Tenant::query()->where('slug', 'cocis')->first();

        if ($tenant === null) {
            $tenant = app(TenantServiceInterface::class)->onboard([
                'name' => 'College of Computing and Information Science',
                'slug' => 'cocis',
                'email' => 'events@cocis.ac.ug',
                'domain' => 'cocis.custospark.events',
                'currency' => 'UGX',
                'timezone' => 'Africa/Kampala',
                'commission_percent' => 5,
                'admin_name' => 'COCIS Events Administrator',
                'admin_email' => env('SEED_COCIS_ADMIN_EMAIL', 'jimisaac8082@gmail.com'),
                'admin_password' => env('SEED_TENANT_ADMIN_PASSWORD', 'Cocis2027!'),
            ]);
        }

        app(TenantContext::class)->set($tenant);

        $admin = User::query()->where('tenant_id', $tenant->id)->orderBy('id')->first();
        $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->first();

        if ($admin !== null && $admin->role_id === null) {
            $admin->update(['role_id' => $adminRole?->id]);
        }

        $this->call(CocisFinalistsDinnerSeeder::class);

        $gulu = Tenant::query()->where('slug', 'gulu')->first();

        if ($gulu === null) {
            app(TenantServiceInterface::class)->onboard([
                'name' => 'Gulu City Events',
                'slug' => 'gulu',
                'email' => 'events@guluevents.ug',
                'domain' => 'gulu.custospark.events',
                'currency' => 'UGX',
                'timezone' => 'Africa/Kampala',
                'commission_percent' => 5,
                'admin_name' => 'Gulu Events Administrator',
                'admin_email' => env('SEED_GULU_ADMIN_EMAIL', 'admin@guluevents.ug'),
                'admin_password' => env('SEED_GULU_ADMIN_PASSWORD', 'Gulu2027!'),
            ]);
        }

        $this->call(GuluEventsSeeder::class);

        app(TenantContext::class)->clear();
    }
}
