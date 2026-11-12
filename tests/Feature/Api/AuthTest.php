<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenantScenario;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreatesTenantScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTenantContext();
    }

    public function test_tenant_administrator_can_log_in(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeAdmin($tenant);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', $admin->email)
            ->assertJsonPath('data.tenant.slug', $tenant->slug)
            ->assertJsonStructure(['data' => ['token', 'user', 'tenant']]);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeAdmin($tenant);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeAdmin($tenant);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $admin->email)
            ->assertJsonPath('data.tenant_id', $tenant->id);
    }

    public function test_attendee_can_register_against_a_tenant(): void
    {
        $tenant = $this->makeTenant();

        Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Attendee',
            'slug' => 'attendee',
            'permissions' => [],
            'is_system' => true,
        ]);

        $this->postJson('/api/v1/auth/register', [
            'tenant_slug' => $tenant->slug,
            'name' => 'Amina Nakato',
            'email' => 'amina@students.cocis.ac.ug',
            'phone' => '0771234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'amina@students.cocis.ac.ug')
            ->assertJsonPath('data.tenant.slug', $tenant->slug);

        $this->assertDatabaseHas('users', [
            'email' => 'amina@students.cocis.ac.ug',
            'tenant_id' => $tenant->id,
        ]);

        $user = User::query()->where('email', 'amina@students.cocis.ac.ug')->first();
        $this->assertTrue(Hash::check('Password123!', $user->password));
    }
}
