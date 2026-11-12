<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Support\CreatesTenantScenario;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use CreatesTenantScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetTenantContext();
    }

    public function test_forgot_password_sends_a_reset_link_to_an_organizer(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $admin = $this->makeAdmin($tenant);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $admin->email,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'If that email address is associated with an organiser account, a password reset link has been sent.');

        Notification::assertSentTo($admin, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@custospark.com',
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_forgot_password_does_not_send_to_attendees(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $attendeeRole = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Attendee',
            'slug' => 'attendee',
            'permissions' => [],
            'is_system' => true,
        ]);
        $attendee = User::query()->create([
            'tenant_id' => $tenant->id,
            'role_id' => $attendeeRole->id,
            'name' => 'Amina Nakato',
            'email' => 'amina@students.cocis.ac.ug',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_platform_admin' => false,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $attendee->email,
        ])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeAdmin($tenant);
        $admin->createToken('web');

        $token = Password::broker()->createToken($admin);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password has been reset successfully.');

        $this->assertTrue(Hash::check('NewPassword123!', $admin->refresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_reset_rejects_an_invalid_token(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeAdmin($tenant);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $admin->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
