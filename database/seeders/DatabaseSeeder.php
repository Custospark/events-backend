<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Contracts\TenantServiceInterface;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $platformPassword = env('SEED_PLATFORM_PASSWORD', 'ChangeMe123!');

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
                'admin_email' => 'admin@cocis.ac.ug',
                'admin_password' => env('SEED_TENANT_ADMIN_PASSWORD', 'ChangeMe123!'),
            ]);
        }

        app(TenantContext::class)->set($tenant);

        if ($tenant->events()->count() === 0) {
            $admin = User::query()->where('tenant_id', $tenant->id)->orderBy('id')->first();
            $adminRole = Role::query()->where('tenant_id', $tenant->id)->where('slug', 'admin')->first();

            if ($admin !== null && $admin->role_id === null) {
                $admin->update(['role_id' => $adminRole?->id]);
            }

            $event = Event::query()->create([
                'tenant_id' => $tenant->id,
                'created_by' => $admin?->id,
                'title' => 'COCIS Annual Tech Conference 2026',
                'slug' => 'cocis-annual-tech-conference-2026',
                'description' => 'A showcase of student innovation, research, and industry partnerships at the College of Computing and Information Science.',
                'venue' => 'COCIS Auditorium',
                'address' => 'Makerere University',
                'city' => 'Kampala',
                'country' => 'Uganda',
                'starts_at' => now()->addDays(30)->setTime(9, 0),
                'ends_at' => now()->addDays(30)->setTime(17, 0),
                'timezone' => 'Africa/Kampala',
                'status' => 'published',
                'capacity' => 800,
                'currency' => 'UGX',
                'service_fee_percent' => 5,
                'published_at' => now(),
            ]);

            $types = [
                ['name' => 'Student', 'price' => 50000, 'quantity_total' => 500, 'sort_order' => 1],
                ['name' => 'Staff', 'price' => 100000, 'quantity_total' => 200, 'sort_order' => 2],
                ['name' => 'Guest', 'price' => 150000, 'quantity_total' => 100, 'sort_order' => 3],
            ];

            foreach ($types as $type) {
                TicketType::query()->create([
                    'tenant_id' => $tenant->id,
                    'event_id' => $event->id,
                    'currency' => 'UGX',
                    'status' => 'active',
                    'min_per_order' => 1,
                    'max_per_order' => 10,
                    ...$type,
                ]);
            }
        }

        app(TenantContext::class)->clear();
    }
}
